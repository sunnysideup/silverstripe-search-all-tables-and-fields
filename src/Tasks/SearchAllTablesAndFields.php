<?php

namespace Sunnysideup\SearchAllTablesAndFields\Tasks;

use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Core\Convert;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DB;

class SearchAllTablesAndFields extends BuildTask
{
    /**
     * Title
     *
     * @var string
     */
    protected $title = 'Search all Tables and Fields using vendor/bin/sake dev/tasks/search-all-tables-and-fields s=SEARCHTERM';

    /**
     * Description
     *
     * @var string
     */
    protected $description = '

        To search for a class name, add four backslashes - e.g. MyApp\\\\\\\\MyClass.
        Options
        r=REPLACETERM: to replace the search term with the replace term.
        i=1: to make the search case sensitive (default for replace)
        f=1: make it a full field match rather than a partial match
        You can

        ';

    /**
     * Enabled
     *
     * @var mixed
     */
    protected $enabled = true;

    /**
     * Segment URL
     *
     * @var string
     */
    private static $segment = 'search-all-tables-and-fields';

    protected $dbName = '';
    protected $searchTermRaw = '';
    protected $replaceTermRaw = '';
    protected $searchTerm = '';
    protected $replaceTerm = '';
    protected $caseSensitive = false;
    protected $fullMatch = false;
    protected $addToStringCharacters = 10;

    /**
     * Run
     *
     * @param HTTPRequest $request HTTP request
     *
     * @return HTTPResponse
     */
    public function run($request)
    {
        if (!Director::is_cli()) {
            exit('Only works in cli');
        }
        $this->getParams($request);

        $this->outputSettings($request);

        if(! $this->searchTerm) {
            exit('Please add a search term using s=SEARCHTERM');
        }

        $this->loopThroughTables($request);

    }

    protected function getParams($request)
    {
        $dbConn = DB::get_conn();
        $this->dbName = $dbConn->getSelectedDatabase();
        $this->searchTermRaw = (string) $request->getVar('s');
        $this->replaceTermRaw = (string) $request->getVar('r');
        $fullMatchRaw = (string) $request->getVar('f');
        $caseSensitiveRaw = strtolower((string) $request->getVar('c'));
        $fullMatchRaw = strtolower((string) $request->getVar('f'));

        $this->searchTerm = Convert::raw2sql($this->searchTermRaw);
        $this->replaceTerm = Convert::raw2sql($this->replaceTermRaw);
        if($this->replaceTerm) {
            $this->caseSensitive =
                $caseSensitiveRaw !== '0' &&
                ($caseSensitiveRaw) !== 'false' &&
                ($caseSensitiveRaw) !== 'no';
            $this->fullMatch =
                $fullMatchRaw !== '0' &&
                ($fullMatchRaw) !== 'false' &&
                ($fullMatchRaw) !== 'no';
        } else {
            $this->caseSensitive =
                $caseSensitiveRaw === '1' ||
                ($caseSensitiveRaw) === 'true' ||
                ($caseSensitiveRaw) === 'yes';
            $this->fullMatch =
                $fullMatchRaw === '1' ||
                ($fullMatchRaw) === 'true' ||
                ($fullMatchRaw) === 'yes';
        }
    }


    protected function outputSettings($request)
    {
        echo $this->description.PHP_EOL;
        echo '-------------------------------'.PHP_EOL;
        echo '-------------------------------'.PHP_EOL;
        echo '-------------------------------'.PHP_EOL;
        echo 'Searching FOR '.$this->searchTermRaw.' '.PHP_EOL;
        if($this->replaceTermRaw) {
            echo '... and replacing with '.$this->replaceTermRaw.PHP_EOL;
        }
        echo $this->caseSensitive ? 'Case Sensitive'.PHP_EOL : 'case insensitive'.PHP_EOL;
        echo '-------------------------------'.PHP_EOL;
        echo '-------------------------------'.PHP_EOL;
        echo '-------------------------------'.PHP_EOL;

    }

    protected function loopThroughTables()
    {
        $sql = "SELECT table_name FROM information_schema.tables WHERE table_schema = '{$this->dbName}'";
        $tables = DB::query($sql);
        // Get all tables in the database
        // Loop through all tables
        foreach ($tables as $table) {
            $tableName = $table["table_name"] ?? $table['TABLE_NAME'];
            $this->replaceForOneTable($tableName);
        }
    }

    protected function replaceForOneTable(string $tableName)
    {


        // Get all columns in the table
        $sql = "SELECT column_name FROM information_schema.columns WHERE table_name = '{$tableName}' AND table_schema = '{$this->dbName}'";
        $columns = DB::query($sql);

        // Build the query
        $conditions = [];

        // Loop through all columns and add a condition for each column
        foreach ($columns as $column) {
            $columnName = $column["column_name"] ?? $column['COLUMN_NAME'];
            $shownCol = false;
            $conditions[] = "";
            if($this->fullMatch) {
                if ($this->caseSensitive) {
                    $sql = "SELECT \"ID\", \"$columnName\" AS C FROM \"{$tableName}\" WHERE BINARY \"{$columnName}\" = BINARY '{$this->searchTerm}' ORDER BY \"ID\" ASC";
                } else {
                    $sql = "SELECT \"ID\", \"$columnName\" AS C FROM \"{$tableName}\" WHERE \"{$columnName}\" = '{$this->searchTerm}' ORDER BY \"ID\" ASC";
                }
            } else {
                if($this->caseSensitive) {
                    $sql = "SELECT \"ID\", \"$columnName\" AS C FROM \"{$tableName}\" WHERE BINARY \"{$columnName}\" LIKE BINARY '%{$this->searchTerm}%' ORDER BY \"ID\" ASC";
                } else {
                    $sql = "SELECT \"ID\", \"$columnName\" AS C FROM \"{$tableName}\" WHERE \"{$columnName}\" LIKE '%{$this->searchTerm}%' ORDER BY \"ID\" ASC";
                }
            }
            $results = DB::query($sql);
            foreach($results as $result) {
                $id = $result['ID'] ?? 0;
                if($shownCol === false) {
                    echo '-------------------------------'.PHP_EOL;
                    echo $tableName.PHP_EOL;
                    echo '-------------------------------'.PHP_EOL;
                    echo '- '.$columnName.PHP_EOL;
                    $shownCol = true;
                }
                echo '  - '.$id.' - '.$this->findWordInString($result['C'], $this->searchTerm).PHP_EOL;
                if($id) {
                    $this->replaceForOneColumnRow($tableName, $columnName, $id);
                } else {
                    echo 'ERROR - skipping row as no ID present.'.PHP_EOL;
                }
            }
        }
    }

    protected function replaceForOneColumnRow(string $tableName, string $columnName, int $id)
    {
        if($this->replaceTerm) {
            DB::alteration_message('Replacing '.$this->searchTermRaw.' with '.$this->replaceTermRaw.' in '.$tableName.'.'.$columnName, 'deleted');
            if ($this->fullMatch) {
                if ($this->caseSensitive) {
                    // Full match, case-sensitive
                    $sql = '
                        UPDATE "'.$tableName.'"
                        SET "'.$columnName.'" = \''.$this->replaceTermRaw.'\'
                        WHERE "'.$columnName.'" = \''.$this->searchTermRaw.'\' AND ID = '.$id;
                } else {
                    // Full match, case-insensitive
                    $sql = '
                        UPDATE "'.$tableName.'"
                        SET "'.$columnName.'" = \''.$this->replaceTermRaw.'\'
                        WHERE LOWER("'.$columnName.'") = LOWER(\''.$this->searchTermRaw.'\') AND ID = '.$id;
                }
            } else {
                if ($this->caseSensitive) {
                    // Partial match, case-sensitive
                    $sql = '
                        UPDATE "'.$tableName.'"
                        SET "'.$columnName.'" = REPLACE("'.$columnName.'", \''.$this->searchTermRaw.'\', \''.$this->replaceTermRaw.'\')
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
                        WHERE LOWER("'.$columnName.'") LIKE \'%'.strtolower($this->searchTermRaw).'%\'';
                }
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
}
