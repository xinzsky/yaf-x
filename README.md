# yaf-x
[yaf](https://github.com/laruence/yaf)是一个C语言编写的PHP MVC框架，性能高但功能很轻量，只实现了MVC框架的一些基本功能，比如：路由、配置、类加载等，yaf-x在yaf的基础上增加了一些web开发常用功能，采用PHP实现，可以作为yaf的全局类库(php.ini中设置yaf.library)来使用，当然也可以作为单独的类库使用。如有问题或讨论请加QQ群：492554661      

**yaf搭配yaf-x在千万级PV的电商网站使用，稳定高效，最赞的是简单、灵活，受框架的限制很少，团队学习成本也低。**  

##yaf-x功能说明
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

## 更多说明
 * 后续会整理出各个模块的详细文档说明，包括yaf的使用文档，[yaf官方文档](http://www.laruence.com/manual/index.html)过于简单，而且有些地方表达不够准确。
 * 还有些功能模块代码没有整理出来，后续也会放出。
 * 代码注释里也有很多实现说明，可以参考一下。
