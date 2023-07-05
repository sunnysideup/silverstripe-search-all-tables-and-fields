<?php

namespace Sunnysideup\SearchAllTablesAndFields\Tasks;

use SilverStripe\Control\Director;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DB;

class SearchAllTablesAndFields extends BuildTask
{
    /**
        * Title
        *
        * @var string
        */
    protected $title = 'Search all Tables and Fields using /dev/tasks/search-all-tables-and-fields?q=SEARCHTERM';

    /**
     * Description
     *
     * @var string
     */
    protected $description = '';

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
    private static $segment = 'ImageDownsizeTask';

    /**
     * Image max width - images larger than this width will be downsized
     *
     * @var int px
     */
    private static $maxImgWidth = 2000;

    /**
     * Image max height - images larger than this height will be downsized
     *
     * @var int px
     */
    private static $maxImgHeight = 2000;

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
        $searchTerm = $request->getVar('q');

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
                $columnName = $column["column_name"];
                $conditions[] = "{$columnName} LIKE '%{$searchTerm}%'";
            }

            // Join all conditions with OR
            $sql = "SELECT ID FROM {$tableName} WHERE " . implode(" OR ", $conditions);

            // Execute the query
            $results = DB::query($sql);

            // Output the results
            foreach($results as $result) {
                echo $result["ID"].PHP_EOL;
            }
        }
    }
}
