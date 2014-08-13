# Install


    composer install


Custom configuration:


    cp config/config.dist.php config/config.php

then start the webserver:

    php -S localhost:8080 -t web web/index.php


## Customized Template


    cp views/custom views/myTemplate;

edit ``config/config.php``:


```php

<?php

return array(
    'template'  => 'myTemplate',
    'sphinx'    =>  array('host'=> 'localhost'),
);

```


## License

MIT, see LICENSE.
