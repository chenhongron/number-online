# number-online
在线直播（心跳检查、人数推送）

本功能在上线运行，负载上千并发，运行良好。
使用PHP第三方中间件workerman（集成pcntl进程管理、posix标准、socket网络编程和select/libevent IO多路复用扩展）
该文件是symfony框架下的command 常驻内存命令模式

当然，你也可以composer WM ：
  composer require workerman/workerman

最后在symfony环境下启动它：
  app/console live:number-online start