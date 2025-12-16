ZZermelo Reporting Engine Running the Examples
========

A PHP reporting engine that works especially well with Laravel, built with love at [Care Set Systems](http://careset.com)


## Running Example
We use a variation on the classic northwind database to test ZZermelo features. We include the schema and data for those databases so that you 
can quickly get some example reports working...

To load the test databases the repo must be cloned into the directory next to the laravel project dir.  Your location will vary depending on your Laravel config.

There is a sample DB table and sample reports based on the Northwind customer database in the example directory of 
the ZZermelo project.

These test databases work for both major Care Set projects: [DURCC](https://github.com/Care Set/DURCC) and ZZermelo (this one).  

1. Load these databases and verify that they exist using your favorite database administration tool.  

    ```
    $ git clone https://github.com/Care Set/MyWind_Test_Data.git
    $ cd MyWind_Test_Data/
    $ php load_databases.php
    ```
    
    To install the sockets data for the NorthwindCustomerSocketReport.php 'mysql source' the data in examples/
    ```
    cd [project-root]
    mysql 
    use _zzermelo_config;
    source vendor/careset/zzermelo/examples/data/_zzermelo_config.northwind_socket_example.sql;
    ```

2. Then copy the example reports from [project-root]/vendor/careset/zzermelo/examples/reports into your app/Reports directory. 
You will need to create the app/Reports directory if it does not exist. From your project root:

    ```
    $ cp vendor/careset/zzermelo/examples/reports/* app/Reports
    ```

Each example report can be accessed using the ZZermelo report url. 
Assuming you have not changed the default urls in the zzermelo configuration, you can load the reports in the following way

Example Report tabular views
``` 
    [base_url]/ZZermelo/NorthwindCustomerReport
```
``` 
    [base_url]/ZZermelo/NorthwindOrderReport
```
``` 
    [base_url]/ZZermelo/NorthwindProductReport
```


