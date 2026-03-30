<?php

namespace Sunnysideup\SearchAllTablesAndFields\Tasks;

use SilverStripe\Core\Convert;
use SilverStripe\Dev\BuildTask;
use SilverStripe\PolyExecution\PolyOutput;
use SilverStripe\ORM\DB;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

class SearchAllTablesAndFields extends BuildTask
{
    /**
     * Title
     *
     * @var string
     */
    protected string $title = 'Search all Tables and Fields using vendor/bin/sake dev/tasks/search-all-tables-and-fields s=SEARCHTERM';

    protected static string $description = '

        To search for a class name, add four backslashes - e.g. MyApp\\\\\\\\MyClass.
        Options
        r=REPLACETERM: to replace the search term with the replace term.
        i=1: to make the search case sensitive (default for replace)
        f=1: make it a full field match rather than a partial match
        You can

        ';

    protected $enabled = true;

    protected static string $commandName = 'search-all-tables-and-fields';

    protected $dbName = '';

    protected $searchTermRaw = '';

    protected $replaceTermRaw = '';

    protected $searchTerm = '';

    protected $replaceTerm = '';

    protected $caseSensitive = false;

    protected $fullMatch = false;

    /**
     * when outputting matches,
     * how many characters should be added to the left and right of the match
     * @var int
     */
    protected $addToStringCharacters = 10;

    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        $this->getParams($input);

        $this->outputSettings($output);

        if (!$this->searchTerm) {
            $output->writeln('Please add a search term using s=SEARCHTERM');
            return Command::INVALID;
        }

        $this->loopThroughTables($output);

        return Command::SUCCESS;

    }

    protected function getParams(InputInterface $input)
    {
        $dbConn = DB::get_conn();
        $this->dbName = $dbConn->getSelectedDatabase();
        $this->searchTermRaw = (string) $input->getOption('search');
        $this->replaceTermRaw = (string) $input->getOption('replace');
        $fullMatchRaw = strtolower((string) $input->getOption('full-match'));
        $caseSensitiveRaw = strtolower((string) $input->getOption('case-sensitive'));
        $fullMatchRaw = strtolower((string) $input->getOption('full-match'));

        $this->searchTerm = Convert::raw2sql($this->searchTermRaw);
        $this->replaceTerm = Convert::raw2sql($this->replaceTermRaw);
        if($this->replaceTerm) {
            $this->caseSensitive =
                !in_array($caseSensitiveRaw, ['0', 'false', 'no'], true);
            $this->fullMatch =
                !in_array($fullMatchRaw, ['0', 'false', 'no'], true);
        } else {
            $this->caseSensitive =
                in_array($caseSensitiveRaw, ['1', 'true', 'yes'], true);
            $this->fullMatch =
                in_array($fullMatchRaw, ['1', 'true', 'yes'], true);
        }
    }


    protected function outputSettings(PolyOutput $output)
    {
        $output->writeln($this->description);
        $output->writeln('-------------------------------');
        $output->writeln('-------------------------------');
        $output->writeln('-------------------------------');
        $output->writeln('Searching FOR '.$this->searchTermRaw.' ');
        if($this->replaceTermRaw) {
            $output->writeln('... and replacing with '.$this->replaceTermRaw);
        }

        $output->writeln($this->caseSensitive ? 'Case Sensitive' : 'case insensitive');
        $output->writeln('-------------------------------');
        $output->writeln('-------------------------------');
        $output->writeln('-------------------------------');

    }

    protected function loopThroughTables(PolyOutput $output)
    {
        $sql = sprintf("SELECT table_name FROM information_schema.tables WHERE table_schema = '%s'", $this->dbName);
        $tables = DB::query($sql);
        // Get all tables in the database
        // Loop through all tables
        foreach ($tables as $table) {
            $tableName = $table["table_name"] ?? $table['TABLE_NAME'];
            $this->replaceForOneTable($tableName, $output);
        }
    }

    protected function replaceForOneTable(string $tableName, PolyOutput $output)
    {

        // Get all columns in the table
        $sql = sprintf("SELECT column_name FROM information_schema.columns WHERE table_name = '%s' AND table_schema = '%s'", $tableName, $this->dbName);
        $columns = DB::query($sql);

        // Loop through all columns and add a condition for each column
        foreach ($columns as $column) {
            $columnName = $column["column_name"] ?? $column['COLUMN_NAME'];
            $shownCol = false;
            $conditions[] = "";
            if ($this->fullMatch) {
                if ($this->caseSensitive) {
                    $sql = sprintf("SELECT \"ID\", \"%s\" AS C FROM \"%s\" WHERE BINARY \"%s\" = BINARY '%s' ORDER BY \"ID\" ASC", $columnName, $tableName, $columnName, $this->searchTerm);
                } else {
                    $sql = sprintf("SELECT \"ID\", \"%s\" AS C FROM \"%s\" WHERE \"%s\" = '%s' ORDER BY \"ID\" ASC", $columnName, $tableName, $columnName, $this->searchTerm);
                }
            } elseif ($this->caseSensitive) {
                $sql = sprintf("SELECT \"ID\", \"%s\" AS C FROM \"%s\" WHERE BINARY \"%s\" LIKE BINARY '%%%s%%' ORDER BY \"ID\" ASC", $columnName, $tableName, $columnName, $this->searchTerm);
            } else {
                $sql = sprintf("SELECT \"ID\", \"%s\" AS C FROM \"%s\" WHERE \"%s\" LIKE '%%%s%%' ORDER BY \"ID\" ASC", $columnName, $tableName, $columnName, $this->searchTerm);
            }

            $results = DB::query($sql);
            foreach($results as $result) {
                $id = $result['ID'] ?? 0;
                if($shownCol === false) {
                    $output->writeln('-------------------------------');
                    $output->writeln($tableName);
                    $output->writeln('-------------------------------');
                    $output->writeln('- '.$columnName);
                    $shownCol = true;
                }

                $output->writeln('  - '.$id.' - '.$this->findWordInString($result['C'], $this->searchTerm));
                if($id) {
                    $this->replaceForOneColumnRow($tableName, $columnName, $id, $output);
                } else {
                    $output->writeln('ERROR - skipping row as no ID present.');
                }
            }
        }
    }

    protected function replaceForOneColumnRow(string $tableName, string $columnName, int $id, PolyOutput $output)
    {
        if($this->replaceTerm) {
            $output->writeln('Replacing '.$this->searchTermRaw.' with '.$this->replaceTermRaw.' in '.$tableName.'.'.$columnName);
            if ($this->fullMatch) {
                if ($this->caseSensitive) {
                    // Full match, case-sensitive
                    $sql = '
                        UPDATE "'.$tableName.'"
                        SET "'.$columnName.'" = \''.$this->replaceTermRaw.'\'
                        WHERE "'.$columnName.'" = \''.$this->searchTermRaw."' AND ID = ".$id;
                } else {
                    // Full match, case-insensitive
                    $sql = '
                        UPDATE "'.$tableName.'"
                        SET "'.$columnName.'" = \''.$this->replaceTermRaw.'\'
                        WHERE LOWER("'.$columnName.'") = LOWER(\''.$this->searchTermRaw."') AND ID = ".$id;
                }
            } elseif ($this->caseSensitive) {
                // Partial match, case-sensitive
                $sql = '
                        UPDATE "'.$tableName.'"
                        SET "'.$columnName.'" = REPLACE("'.$columnName.'", \''.$this->searchTermRaw."', '".$this->replaceTermRaw.'\')
                        WHERE ID = '.$id;
            } else {
                // Partial match, case-insensitive
                $sql = '
                        UPDATE "'.$tableName.'"
                        SET "'.$columnName.'" = CONCAT(
                            LEFT("'.$columnName.'", LOCATE(LOWER(\''.$this->searchTermRaw.'\'), LOWER("'.$columnName.'")) - 1),
                            \''.$this->replaceTermRaw.'\',
                            SUBSTRING("'.$columnName.'", LOCATE(LOWER(\''.$this->searchTermRaw.'\'), LOWER("'.$columnName.'")) + LENGTH(\''.$this->searchTermRaw.'\'))
                        )
                        WHERE LOWER("'.$columnName.'") LIKE \'%'.strtolower((string) $this->searchTermRaw)."%'";
            }

            DB::query($sql);
        }
    }

    protected function findWordInString(string $resultString, string $searchTerm, ?bool $caseSentive = false): string
    {
        $output = '';
        $searchTermLength = strlen($searchTerm);
        $offset = 0;
        $resultLength = strlen($resultString);
        if($resultLength < $searchTermLength + ($this->addToStringCharacters * 2)) {
            return $resultString;
        }

        $method = 'stripos';
        if($caseSentive) {
            $method = 'strpos';
        }

        while (($pos = $method($resultString, $searchTerm, $offset)) !== false) {
            $start = max($pos - $this->addToStringCharacters, 0);
            $length = $searchTermLength + ($this->addToStringCharacters * 2); // 10 characters before + word + 10 characters after

            // If start is less than 10, length needs to be reduced
            if ($pos < $this->addToStringCharacters) {
                $length -= ($this->addToStringCharacters - $pos);
            }

            if($start + $length > $resultLength) {
                $length = $resultLength - $start;
            }

            $snippet = substr($resultString, $start, $length);
            $output .= '...' . $snippet;


            // Update offset to start searching from the end of this match
            $offset = $pos + $searchTermLength;
        }

        return $output;
    }

    public function getOptions(): array
    {
        return [
            ['search', 's', InputOption::VALUE_REQUIRED, 'Search term'],
            ['replace', 'r', InputOption::VALUE_OPTIONAL, 'Replacement term'],
            ['case-sensitive', 'c', InputOption::VALUE_OPTIONAL, 'Case sensitive search flag'],
            ['full-match', 'f', InputOption::VALUE_OPTIONAL, 'Full match flag'],
        ];
    }
}
