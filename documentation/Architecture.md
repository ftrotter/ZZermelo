ZZermelo Reporting Engine
========

A PHP reporting engine that works especially well with Laravel, built with love at [Care Set Systems](http://careset.com)


Architecture
------------------

![ZZermelo Data Flow Diagram](https://raw.githubusercontent.com/Care Set/ZZermelo/master/documentation/ZZermelo_Reporting_Engine_Design.png)

Basically the way ZZermelo works is to run your SQL against your data... then put it into a cache table (usually in the \_zzermelo database)
Then it does its paging and sorting against that cached version of your data.  
