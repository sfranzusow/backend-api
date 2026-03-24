# Laravel lokale Hinweise

## Nach .env-Änderungen
php artisan optimize:clear

## Wenn SESSION_DRIVER=database
php artisan migrate

## Bei komischen Fehlern
tail -n 50 storage/logs/laravel.log

## stoppen von php server an bestimtem port

lsof -i :PORT_ALS_ZAHL

#### Dann siehst du die PID. Danach z. B.:

kill -9 ProzessIdAlsZahl