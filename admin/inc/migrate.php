<?php
/** Executor de migrações: aplica admin/sql/NNN_*.sql que ainda não rodaram. */

declare(strict_types=1);

function migration_files(): array
{
    $files = glob(ADMIN_DIR . '/sql/*.sql') ?: [];
    sort($files);
    return $files;
}

function migrations_done(): array
{
    db()->exec('CREATE TABLE IF NOT EXISTS migrations (name VARCHAR(120) NOT NULL PRIMARY KEY, ran_at DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    return array_column(q_all('SELECT name FROM migrations'), 'name');
}

function migrations_pending(): array
{
    $done = migrations_done();
    return array_values(array_filter(migration_files(), fn($f) => !in_array(basename($f), $done, true)));
}

/** @return string[] log */
function run_migrations(): array
{
    $log = [];
    foreach (migrations_pending() as $file) {
        $name = basename($file);
        $sql = (string) file_get_contents($file);
        $stmts = preg_split('/;\s*[\r\n]+/', trim($sql));
        foreach ($stmts as $s) {
            $s = trim($s);
            $s = trim(preg_replace('/^\s*--.*$/m', '', $s));
            if ($s === '' || $s === ';') continue;
            db()->exec($s);   // DDL do MySQL confirma sozinho; cada arquivo roda em ordem
        }
        q('INSERT INTO migrations (name, ran_at) VALUES (?, NOW())', [$name]);
        $log[] = "OK  $name";
    }
    if (!$log) $log[] = 'Nada a fazer: o banco já está na versão mais nova.';
    return $log;
}
