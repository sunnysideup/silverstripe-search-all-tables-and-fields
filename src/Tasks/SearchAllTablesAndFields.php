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
    protected $title = 'Search all Tables and Fields using /dev/tasks/search-all-tables-and-fields?s=SEARCHTERM';

    /**
     * Description
     *
     * @var string
     */
    protected $description = '
        To search for a class name, add four backslashes - e.g. MyApp\\\\\\\\MyClass
        You can add &r=REPLACETERM to replace the search term with the replace term.
        You can add i=1 to make the search case sensitive.
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
        echo $this->description.PHP_EOL;
        $dbConn = DB::get_conn();
        $dbName = $dbConn->getSelectedDatabase();
        $searchTermRaw = (string) $request->getVar('s');
        $replaceTermRaw = (string) $request->getVar('r');
        $caseSensitiveRaw = strtolower((string) $request->getVar('c'));

        $searchTerm = Convert::raw2sql($searchTermRaw);
        $replaceTerm = Convert::raw2sql($replaceTermRaw);
        if($replaceTerm) {
            $caseSensitive =
                $caseSensitiveRaw !== '0' &&
                ($caseSensitiveRaw) !== 'false' &&
                ($caseSensitiveRaw) !== 'no';
        } else {
            $caseSensitive =
                $caseSensitiveRaw === '1' ||
                ($caseSensitiveRaw) === 'true' ||
                ($caseSensitiveRaw) === 'yes';
        }
        // Get all tables in the database
        $sql = "SELECT table_name FROM information_schema.tables WHERE table_schema = '{$dbName}'";
        $tables = DB::query($sql);
        echo '-------------------------------'.PHP_EOL;
        echo '-------------------------------'.PHP_EOL;
        echo '-------------------------------'.PHP_EOL;
        echo 'Searching FOR '.$searchTermRaw.' '.PHP_EOL;
        if($replaceTermRaw) {
            echo '... and replacing with '.$replaceTermRaw.PHP_EOL;
        }
        echo $caseSensitive ? 'Case Sensitive'.PHP_EOL : 'case insensitive'.PHP_EOL;
        echo '-------------------------------'.PHP_EOL;
        echo '-------------------------------'.PHP_EOL;
        echo '-------------------------------'.PHP_EOL;
        // Loop through all tables
        foreach ($tables as $table) {
            $tableName = $table["table_name"] ?? $table['TABLE_NAME'];



            // Get all columns in the table
            $sql = "SELECT column_name FROM information_schema.columns WHERE table_name = '{$tableName}' AND table_schema = '{$dbName}'";
            $columns = DB::query($sql);

            // Build the query
            $conditions = [];

            // Loop through all columns and add a condition for each column
            foreach ($columns as $column) {
                $columnName = $column["column_name"] ?? $column['COLUMN_NAME'];
                $shownCol = false;
                $conditions[] = "";
                if($caseSensitive) {
                    $sql = "SELECT \"ID\", \"$columnName\" AS C FROM \"{$tableName}\" WHERE BINARY \"{$columnName}\" LIKE BINARY '%{$searchTerm}%' ORDER BY \"ID\" ASC";
                } else {
                    $sql = "SELECT \"ID\", \"$columnName\" AS C FROM \"{$tableName}\" WHERE \"{$columnName}\" LIKE '%{$searchTerm}%' ORDER BY \"ID\" ASC";

                }
                $results = DB::query($sql);
                foreach($results as $result) {
                    if($shownCol === false) {
                        echo '-------------------------------'.PHP_EOL;
                        echo $tableName.PHP_EOL;
                        echo '-------------------------------'.PHP_EOL;
                        echo '- '.$columnName.PHP_EOL;
                        $shownCol = true;
                    }
                    echo '  - '.$result['ID'].' - '.$this->findWordInString($result['C'], $searchTerm).PHP_EOL;
                    if($replaceTerm) {
                        DB::alteration_message('Replacing '.$searchTermRaw.' with '.$replaceTermRaw.' in '.$tableName.'.'.$columnName, 'deleted');
                        if($caseSensitive) {
                            $sql = "
                            UPDATE \"{$tableName}\"
                            SET \"{$columnName}\" = REPLACE(\"{$columnName}\", '{$searchTermRaw}', '{$replaceTermRaw}')
                            WHERE ID = {$result['ID']}";
                        } else {
                            $sql = "
                            UPDATE \"{$tableName}\"
                            SET \"{$columnName}\" = CONCAT(
                                LEFT(\"{$columnName}\", LOCATE(LOWER('{$searchTermRaw}'), LOWER(\"{$columnName}\")) - 1),
                                '{$replaceTermRaw}',
                                SUBSTRING(\"{$columnName}\", LOCATE(LOWER('{$searchTermRaw}'), LOWER(\"{$columnName}\")) + LENGTH('{$searchTermRaw}'))
                            )
                            WHERE LOWER(\"{$columnName}\") LIKE '%".strtolower($searchTermRaw)."%';

                            ";
                        }

                        DB::query($sql);
                    }
                }
            }

        }
    }

    protected function findWordInString(string $resultString, string $searchTerm, ?bool $caseSentive = false): string
    {
        $output = '';
        $searchTermLength = strlen($searchTerm);
        $offset = 0;
        $resultLength = strlen($resultString);
        if($resultLength < $searchTermLength + 20) {
            return $resultString;
        }
        $method = 'stripos';
        if($caseSentive) {
            $method = 'strpos';
        }
        while (($pos = $method($resultString, $searchTerm, $offset)) !== false) {
            $start = max($pos - 10, 0);
            $length = $searchTermLength + 20; // 10 characters before + word + 10 characters after

            // If start is less than 10, length needs to be reduced
            if ($pos < 10) {
                $length -= (10 - $pos);
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
