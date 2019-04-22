<?php
namespace Topxia\WebBundle\Command;
require_once( dirname(__FILE__) . '/../../../../vendor_user/workerman/vendor/autoload.php');

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Workerman\Lib\Timer;

class LiveOnlineCommand extends BaseCommand
{
    //socket地址
    const SOCKET_NAME = 'websocket://0.0.0.0:8680';
    // 心跳间隔（秒）
    const HEARTBEAT_TIME = 30;
    // 定时器（秒）
    const TIMER = 10;
    
    protected function configure()
    {
        $this->setName ( 'live:number-online')
            ->addArgument('wmCommand', null, 'workerman命令')
            ->setDescription('在线直播（心跳检查、人数推送）');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {    
        $worker = new BaseWorker(self::SOCKET_NAME);
        
        $worker->onMessage = function($connection, $msg)use($worker) {
            $connection->lastMessageTime = time();
            // 1表示新的连接    0表示client的心跳回应
            if ($msg == 1) {
                $this->sendNumsToClient($worker->connections);
            }
        };

        $worker->onWorkerStart = function($worker) {
            // 定时器
            Timer::add(self::TIMER, function()use($worker){
                $timeNow = time();
                foreach($worker->connections as $connection) {
                    //心跳检测
                    $connection->send(0);
                    
                    // 有可能该connection还没收到过消息
                    if (empty($connection->lastMessageTime)) {
                        $connection->lastMessageTime = $timeNow;
                        continue;
                    }
                    
                    // 心跳超时
                    if ($timeNow - $connection->lastMessageTime > self::HEARTBEAT_TIME) {
                        $connection->close();
                    }
                }
            });
        };
        
        $worker->onClose = function($connection)use($worker) {
            $connections = $worker->connections;
            unset($connections[$connection->id]);
            $this->sendNumsToClient($connections);
        };
        
        BaseWorker::runAll();
    }
    
    /**
     * 向cline端发送在线人数
     */
    protected function sendNumsToClient($connections=array())
    {        
        $nums = count($connections);
        foreach($connections as $connection) {
            $connection->send($nums);
        }
    }

}