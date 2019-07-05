<?php
/**
 * 重写workerman,以适应symfony command
 *
 * @author    Ron<chenhongron@163.com>
 */

namespace Topxia\WebBundle\Command;

use Workerman\Worker;

/**
 * Worker class
 * A container for listening ports
 */
class BaseWorker extends Worker
{

    /**
     * Parse command.
     *
     * @return void
     */
    protected static function parseCommand()
    {
        if (static::$_OS !== OS_TYPE_LINUX) {
            return;
        }
        global $argv;
        // Check argv;
        $start_file = $argv[0];
        $available_commands = array(
            'start',
            'stop',
            'restart',
            'reload',
            'status',
            'connections',
        );
        $usage = "Usage: php yourfile <command> [mode]\nCommands: \nstart\t\tStart worker in DEBUG mode.\n\t\tUse mode -d to start in DAEMON mode.\nstop\t\tStop worker.\n\t\tUse mode -g to stop gracefully.\nrestart\t\tRestart workers.\n\t\tUse mode -d to start in DAEMON mode.\n\t\tUse mode -g to stop gracefully.\nreload\t\tReload codes.\n\t\tUse mode -g to reload gracefully.\nstatus\t\tGet worker status.\n\t\tUse mode -d to show live status.\nconnections\tGet worker connections.\n";
        if (!isset($argv[2]) || !in_array($argv[2], $available_commands)) {
            if (isset($argv[2])) {
                static::safeEcho('Unknown command: ' . $argv[2] . "\n");
            }
            exit($usage);
        }

        // Get command.
        $command  = trim($argv[2]);
        $command2 = isset($argv[3]) ? $argv[3] : '';

        // Start command.
        $mode = '';
        if ($command === 'start') {
            if ($command2 === '-d' || static::$daemonize) {
                $mode = 'in DAEMON mode';
            } else {
                $mode = 'in DEBUG mode';
            }
        }
        static::log("Workerman[$start_file] $command $mode");

        // Get master process PID.
        $master_pid      = is_file(static::$pidFile) ? file_get_contents(static::$pidFile) : 0;
        $master_is_alive = $master_pid && posix_kill($master_pid, 0) && posix_getpid() != $master_pid;
        // Master is still alive?
        if ($master_is_alive) {
            if ($command === 'start') {
                static::log("Workerman[$start_file] already running");
                exit;
            }
        } elseif ($command !== 'start' && $command !== 'restart') {
            static::log("Workerman[$start_file] not run");
            exit;
        }

        // execute command.
        switch ($command) {
            case 'start':
                if ($command2 === '-d') {
                    static::$daemonize = true;
                }
                break;
            case 'status':
                while (1) {
                    if (is_file(static::$_statisticsFile)) {
                        @unlink(static::$_statisticsFile);
                    }
                    // Master process will send SIGUSR2 signal to all child processes.
                    posix_kill($master_pid, SIGUSR2);
                    // Sleep 1 second.
                    sleep(1);
                    // Clear terminal.
                    if ($command2 === '-d') {
                        static::safeEcho("\33[H\33[2J\33(B\33[m", true);
                    }
                    // Echo status data.
                    static::safeEcho(static::formatStatusData());
                    if ($command2 !== '-d') {
                        exit(0);
                    }
                    static::safeEcho("\nPress Ctrl+C to quit.\n\n");
                }
                exit(0);
            case 'connections':
                if (is_file(static::$_statisticsFile) && is_writable(static::$_statisticsFile)) {
                    unlink(static::$_statisticsFile);
                }
                // Master process will send SIGIO signal to all child processes.
                posix_kill($master_pid, SIGIO);
                // Waiting amoment.
                usleep(500000);
                // Display statisitcs data from a disk file.
                if(is_readable(static::$_statisticsFile)) {
                    readfile(static::$_statisticsFile);
                }
                exit(0);
            case 'restart':
            case 'stop':
                if ($command2 === '-g') {
                    static::$_gracefulStop = true;
                    $sig = SIGTERM;
                    static::log("Workerman[$start_file] is gracefully stopping ...");
                } else {
                    static::$_gracefulStop = false;
                    $sig = SIGINT;
                    static::log("Workerman[$start_file] is stopping ...");
                }
                // Send stop signal to master process.
                $master_pid && posix_kill($master_pid, $sig);
                // Timeout.
                $timeout    = 5;
                $start_time = time();
                // Check master process is still alive?
                while (1) {
                    $master_is_alive = $master_pid && posix_kill($master_pid, 0);
                    if ($master_is_alive) {
                        // Timeout?
                        if (!static::$_gracefulStop && time() - $start_time >= $timeout) {
                            static::log("Workerman[$start_file] stop fail");
                            exit;
                        }
                        // Waiting amoment.
                        usleep(10000);
                        continue;
                    }
                    // Stop success.
                    static::log("Workerman[$start_file] stop success");
                    if ($command === 'stop') {
                        exit(0);
                    }
                    if ($command2 === '-d') {
                        static::$daemonize = true;
                    }
                    break;
                }
                break;
            case 'reload':
                if($command2 === '-g'){
                    $sig = SIGQUIT;
                }else{
                    $sig = SIGUSR1;
                }
                posix_kill($master_pid, $sig);
                exit;
            default :
                if (isset($command)) {
                    static::safeEcho('Unknown command: ' . $command . "\n");
                }
                exit($usage);
        }
    }

}
