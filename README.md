#combodo-monitoring

Allow to monitor iTop from Prometheus.

URL:
http://localhost/iTop/pages/exec.php?exec_module=combodo-monitoring&exec_page=index.php&exec_env=production&auth_user=admin&auth_pwd=admin


## configuration

create conf/production/monitoring-itop.ini

#oql metric
itop_user_count[description]=Nb of users
itop_user_count[oql_count]=SELECT User

#itop conf metric
itop_backup_retention_count[description]=Nb of users
itop_backup_retention_count[conf]=MyModuleSettings.itop-backup.retention_count
