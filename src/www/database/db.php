<?php
/**
 * Simple example of extending the SQLite3 class and changing the __construct
 * parameters, then using the open method to initialize the DB.
 */
class MyDB extends SQLite3
{
    function __construct()
    {
        $this->open('/db/db.db');
        // Enable WAL mode for better concurrency
        $this->exec('PRAGMA journal_mode=WAL;');
        // Set busy timeout
        $this->busyTimeout(5000);
    }
}
?>
