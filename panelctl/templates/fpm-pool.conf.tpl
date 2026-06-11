[{{domain}}]
user = {{user}}
group = {{user}}

listen = {{socket}}
listen.owner = www-data
listen.group = www-data
listen.mode = 0660

pm = ondemand
pm.max_children = 10
pm.process_idle_timeout = 30s
pm.max_requests = 500

php_admin_value[error_log] = {{home}}/logs/php-error.log
php_admin_flag[log_errors] = on
php_admin_value[session.save_path] = {{home}}/tmp
php_admin_value[upload_tmp_dir] = {{home}}/tmp
php_admin_value[sys_temp_dir] = {{home}}/tmp
php_admin_value[memory_limit] = {{memory_limit}}
php_admin_value[upload_max_filesize] = {{upload_max_filesize}}
php_admin_value[post_max_size] = {{post_max_size}}
php_admin_value[max_execution_time] = {{max_execution_time}}
php_admin_flag[display_errors] = {{display_errors}}
php_admin_flag[expose_php] = off
php_admin_value[disable_functions] = exec,passthru,shell_exec,system,proc_open,popen
