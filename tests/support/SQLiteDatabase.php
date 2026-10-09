<?php

declare(strict_types=1);

// An isolated benchmark database. Production uses the configured MySQL or PostgreSQL database.
class SQLiteStatements {
    public PDO $connection;
    public int $statements = 0;

    public function __construct(string $path) {
        $this->connection = new PDO('sqlite:'.$path);
        $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function preparedStatement($sql, $params = [], $fetchResponse = false, $paramTypes = []) {
        ++$this->statements;
        $statement = $this->connection->prepare($sql);
        foreach (array_values($params) as $index => $value) {
            $type = $paramTypes[$index] ?? (str_starts_with($sql, 'INSERT') && $index === 5 ? PDO::PARAM_LOB : PDO::PARAM_STR);
            $statement->bindValue($index + 1, $value, $type);
        }
        $statement->execute();
        return $fetchResponse ? $statement->fetchAll(PDO::FETCH_ASSOC) : true;
    }

    public function preparedRows($sql, $params = [], $buffered = true): Generator {
        ++$this->statements;
        $statement = $this->connection->prepare($sql);
        $statement->execute(array_values($params));
        try {
            while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
                yield $row;
            }
        } finally {
            $statement->closeCursor();
        }
    }
}

class SQLiteDatabase extends LGV_TZ_Lookup_Database {
    public function __construct(string $path) {
        $this->pdo_instance = new SQLiteStatements($path);
    }

    public function reset_database() {
        $this->pdo_instance->connection->exec('DROP TABLE IF EXISTS timezones;
            CREATE TABLE timezones (
                id INTEGER PRIMARY KEY AUTOINCREMENT, tzname TEXT NOT NULL,
                east REAL NOT NULL, west REAL NOT NULL, north REAL NOT NULL, south REAL NOT NULL,
                polygon BLOB NOT NULL
            );
            CREATE INDEX east ON timezones(east);
            CREATE INDEX west ON timezones(west);
            CREATE INDEX north ON timezones(north);
            CREATE INDEX south ON timezones(south);');
    }
}
