# WordPress login backend

Integrate WordPress login with Nextcloud

## What this app do?

Autenticate at Nextcloud using username and password of WordPress.

## How to configure
```bash
occ config:system:set wordpress_dsn --value "mysql:host=myserverhostname;port=3306;dbname=woocommerce;user=root;password=root"
```

Replace the values by your databae settings.


## to-do

- [ ] Customize the queries to fetch data from WordPress
