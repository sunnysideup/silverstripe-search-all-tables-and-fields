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
    protected $title = 'Search all Tables and Fields using /dev/tasks/search-all-tables-and-fields?q=SEARCHTERM, you can add &r=REPLACETERM to replace the search term with the replace term.';

    /**
     * Description
     *
     * @var string
     */
    protected $description = 'Goes through ALL the tables and fields and ouputs a list of IDs with matches in the tables and IDs for Rows';

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
        $dbConn = DB::get_conn();
        $dbName = $dbConn->getSelectedDatabase();
        $searchTerm = Convert::raw2sql($request->getVar('q'));
        $replaceTerm = Convert::raw2sql($request->getVar('r'));

        // Get all tables in the database
        $sql = "SELECT table_name FROM information_schema.tables WHERE table_schema = '{$dbName}'";
        $tables = DB::query($sql);

        // Loop through all tables
        foreach ($tables as $table) {
            $tableName = $table["table_name"];

            echo '-------------------------------'.PHP_EOL;
            echo $tableName.PHP_EOL;
            echo '-------------------------------'.PHP_EOL;

            // Get all columns in the table
            $sql = "SELECT column_name FROM information_schema.columns WHERE table_name = '{$tableName}'";
            $columns = DB::query($sql);

            // Build the query
            $conditions = [];

            // Loop through all columns and add a condition for each column
            foreach ($columns as $column) {
                echo '- '.$column.PHP_EOL;
                $columnName = $column["column_name"];
                $conditions[] = "";
                $sql = "SELECT ID, $columnName FROM {$tableName} WHERE {$columnName} LIKE '%{$searchTerm}%'";
                $results = DB::query($sql);
                foreach($results as $result) {
                    echo '  - '.implode('|', $result).PHP_EOL;
                    if($replaceTerm) {
                        $sql = "
                            UPDATE {$tableName}
                            SET {$columnName} = REPLACE({$columnName}, '{$searchTerm}', '{$replaceTerm}')
                            WHERE ID = {$result['ID']}";
                        DB::query($sql);
                    }
                }
            }

        }
    }
}
