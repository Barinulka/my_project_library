

## Запуск тестов
### Подготовка тестовой БД 
#### Cоздание тестовой БД
```
php bin/console doctrine:database:create --env=test
```
#### Применение миграций на тестовоую БД
```
php bin/console doctrine:migrations:migrate --env=test
```
#### Проверка на ошибки тестовой БД
```
php bin/console doctrine:schema:validate --env=test
```
### Запуск тестов
```
php bin/phpunit tests --testdox
```
