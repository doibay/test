<?php

$config['db']['host'] = '$_ENV['OPENSHIFT_MYSQL_DB_HOST']';
$config['db']['port'] = '$_ENV['OPENSHIFT_MYSQL_DB_PORT']';
$config['db']['username'] = '$_ENV['OPENSHIFT_MYSQL_DB_USERNAME']';
$config['db']['password'] = '$_ENV['OPENSHIFT_MYSQL_DB_PASSWORD']';
$config['db']['dbname'] = '$_ENV['OPENSHIFT_APP_NAME']';


$config['superAdmins'] = '1';