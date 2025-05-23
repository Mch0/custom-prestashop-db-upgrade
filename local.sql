update ps_shop_url set domain = '', domain_ssl = '';
update ps_configuration set value = '' where name like "%DOMAIN%";