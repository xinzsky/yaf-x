# yaf-x
[yaf](https://github.com/laruence/yaf)是一个C语言编写的PHP MVC框架，性能很高但功能很轻量，只实现了MVC框架的基本功能，比如：路由、配置、类加载等，yaf-x就是在yaf的基础上增加了一些web开发的常用功能，采用PHP开发，可以作为yaf的全局类库来使用。  
yaf搭配yaf-x在千万级PV的电商网站使用过，性能高、稳定。

##功能说明
* 基本类
  + Error.php ---------- 错误码、错误信息定义
  + Exception.php ------ 全局异常处理
  + Const.php ---------- 常量定义
  + Log.php ------------ 日志
* 数据库、缓存类
  + Db.php ------------- 封装MySQL 5 PDO，增加单例模式、重连、防SQL注入、ping等功能
  + Sharding.php ------- 支持数据库水平切分、读写分离、一主多从、从库负载均衡等
  + Cache.php ---------- 基于memcached和ketama分布式一致性hash算法的缓存类
* 安全、会话类
  + Security.php ------- 解决网站安全问题：SQL注入、XSS攻击、CSRF、机器人、爬虫、密码存放、帐号安全等，详情请看代码注释。
  + Input.php ---------- 对用户输入数据进行验证、处理，保证用户输入数据合法。
  + Output.php --------- 用于安全的把数据输出到页面上，避免XSS攻击。
  + Session.php -------- 支持memcached来存储session，这样可以使用多台服务器来部署PHP。
* 请求、响应类
  + Request.php -------- 在YAF Request类的基础上增加了获取：UserIP RemoteIP  UserAgent Platform browser mobile robot Refferer Cookie等
  + Response.php ------- 在YAF Reponse类的基础上增加了生成一些特殊header和body输出的功能。
* 常用功能类
  + Captcha.php -------- 生成验证码
  + Pagination.php ----- 分页
  + Upload.php --------- 文件、图片上传，图片上传可以指定是否生成缩略图。
* 实用类
  + Utils.php ---------- 一些实用函数集
  + utils/Adminregion.php 行政区划
  + utils/Email.php ---- 发邮件
  + utils/Ip.php ------- IP查询，来源于高春辉的17MON.CN
  + utils/Mptt.php ----- 可以用来生成层次树状结构
  + utils/Thumbnail.php- 图片缩略
  + utils/Unihan.php --- 繁体汉字转简体
  + utils/Kses5.php ---- HTML过滤(HTML/XHTML filter that only allows some elements and attributes)
  + utils/kses.php ----- HTML/XHTML filter that only allows some elements and attributes
