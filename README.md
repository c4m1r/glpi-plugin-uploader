# GLPI Plugin Uploader

Одностраничный PHP скрипт для удобной загрузки и установки плагинов GLPI через веб-интерфейс с drag & drop функциональностью.

## Назначение

Скрипт позволяет:
- Загружать архивы плагинов GLPI (.zip, .tar, .7z)
- Автоматически распаковывать их во временную директорию
- Копировать содержимое в директорию `/var/www/glpi/plugins`
- Автоматически устанавливать правильные права доступа (www-data:www-data, 755)
- Отображать подробные логи выполнения операций

## Требования

### Системные требования
- PHP 7.4+ с модулями:
  - `php-zip` (для работы с ZIP архивами)
  - `php-mbstring`
- Веб-сервер (Apache/Nginx)
- Установленный GLPI в `/var/www/glpi`

### Необходимое ПО
```bash
# Ubuntu/Debian
sudo apt update
sudo apt install php php-zip p7zip-full unzip tar

# CentOS/RHEL
sudo yum install php php-zip p7zip unzip tar
```

## Установка

### 1. Размещение скрипта
```bash
# Создайте директорию для скрипта
sudo mkdir -p /var/www/plugin-uploader

# Скопируйте index.php в эту директорию
sudo cp index.php /var/www/plugin-uploader/

# Установите права доступа
sudo chown -R www-data:www-data /var/www/plugin-uploader
sudo chmod -R 755 /var/www/plugin-uploader
```

### 2. Настройка веб-сервера

#### Apache (в /etc/apache2/sites-available/plugin-uploader.conf):
```apache
<VirtualHost *:80>
    ServerName plugin-uploader.local
    DocumentRoot /var/www/plugin-uploader

    <Directory /var/www/plugin-uploader>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/plugin-uploader_error.log
    CustomLog ${APACHE_LOG_DIR}/plugin-uploader_access.log combined
</VirtualHost>
```

Включите сайт:
```bash
sudo a2ensite plugin-uploader
sudo systemctl reload apache2
```

#### Nginx (в /etc/nginx/sites-available/plugin-uploader):
```nginx
server {
    listen 80;
    server_name plugin-uploader.local;
    root /var/www/plugin-uploader;
    index index.php;

    location / {
        try_files $uri $uri/ =404;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
    }

    location ~ /\.ht {
        deny all;
    }
}
```

Включите сайт:
```bash
sudo ln -s /etc/nginx/sites-available/plugin-uploader /etc/nginx/sites-enabled/
sudo systemctl reload nginx
```

### 3. Установка скрипта установки прав

Скопируйте скрипт установки прав:

```bash
# Скопируйте скрипт в системную директорию
sudo cp glpi_fix_perms.sh /usr/local/bin/

# Сделайте его исполняемым
sudo chmod +x /usr/local/bin/glpi_fix_perms.sh

# Установите владельца
sudo chown root:root /usr/local/bin/glpi_fix_perms.sh
```

### 4. Настройка sudo для www-data

Создайте файл sudoers с помощью visudo:

```bash
sudo visudo -f /etc/sudoers.d/glpi-plugin-uploader
```

Добавьте в файл следующее содержимое:

```bash
# Разрешаем www-data запуск ТОЛЬКО скрипт прав без пароля
www-data ALL=(root) NOPASSWD: /usr/local/bin/glpi_fix_perms.sh
```

Установите правильные права:
```bash
sudo chmod 440 /etc/sudoers.d/glpi-plugin-uploader
```

### Проверка sudo прав

```bash
sudo -u www-data sudo -l
```

Ожидаемый вывод:
```
(root) NOPASSWD: /usr/local/bin/glpi_fix_perms.sh
```

Это снимает **все вопросы** про пароль.

### 4. Проверка прав доступа

Убедитесь, что директория GLPI plugins доступна для записи:
```bash
# Проверьте текущие права
ls -la /var/www/glpi/plugins

# Если нужно, установите права
sudo chown -R www-data:www-data /var/www/glpi/plugins
sudo chmod -R 755 /var/www/glpi/plugins
```

### 5. Настройка hosts файла

Добавьте в `/etc/hosts`:
```
127.0.0.1 plugin-uploader.local
```

## Использование

1. Откройте браузер и перейдите по адресу: `http://plugin-uploader.local`
2. Введите пароль: `123123`
3. Перетащите архив плагина (.zip, .tar или .7z) в выделенную область или нажмите "Выбрать файл"
4. Нажмите "Загрузить и установить"
5. Дождитесь завершения процесса и проверьте логи

## Безопасность

⚠️ **Важные предупреждения:**

1. **Пароль по умолчанию**: Измените пароль `123123` в коде скрипта на более безопасный
2. **Ограниченный доступ**: Используйте скрипт только в локальной сети или с дополнительной аутентификацией
3. **Временные файлы**: Скрипт автоматически удаляет временные файлы после обработки
4. **Размер файлов**: Ограничение в 100MB на файл
5. **Типы файлов**: Разрешены только архивы .zip, .tar, .7z
6. **Права sudo**: Ограничены только выполнением специализированного скрипта установки прав

## Структура обработки файла

1. **Загрузка**: Файл проверяется на размер и тип
2. **Распаковка**: Создается временная директория `/tmp/glpi_upload_XXXX`
3. **Анализ структуры**:
   - Если архив содержит одну корневую папку - она копируется в plugins
   - Если несколько файлов - создается папка с именем архива
4. **Копирование**: Файлы копируются в `/var/www/glpi/plugins`
5. **Права**: Выполняется специализированный bash скрипт с правами root через sudo (NOPASSWD)
6. **Очистка**: Временные файлы удаляются

## Логи

Скрипт отображает подробные логи выполнения:
- Создание временных директорий
- Распаковка архива
- Копирование файлов
- Установка прав
- Ошибки выполнения

## Удаление скрипта

После завершения установки всех необходимых плагинов:

```bash
# Удалите директорию скрипта
sudo rm -rf /var/www/plugin-uploader

# Удалите bash скрипт установки прав
sudo rm /usr/local/bin/glpi_fix_perms.sh

# Удалите конфигурацию веб-сервера
sudo a2dissite plugin-uploader  # для Apache
sudo rm /etc/nginx/sites-enabled/plugin-uploader  # для Nginx

# Удалите настройки sudo
sudo rm /etc/sudoers.d/glpi-plugin-uploader

# Перезагрузите сервисы
sudo systemctl reload apache2  # или nginx
```

## Возможные проблемы

### Ошибка "Не удалось выполнить распаковку"
- Убедитесь, что установлены `p7zip-full`, `unzip`, `tar`
- Проверьте права доступа к `/tmp`

### Ошибка "Не удалось скопировать файлы"
- Проверьте права доступа к `/var/www/glpi/plugins`
- Убедитесь, что GLPI установлен

### Ошибка sudo команд
- Проверьте файл `/etc/sudoers.d/glpi-plugin-uploader`
- Убедитесь, что скрипт `/usr/local/bin/glpi_fix_perms.sh` существует и исполняемый
- Проверьте логи выполнения скрипта в выводе
- Перезагрузите веб-сервер после изменений

### Файл не загружается
- Проверьте `upload_max_filesize` и `post_max_size` в php.ini
- Убедитесь, что размер файла не превышает 100MB

## Кастомизация

### Изменение пароля
```php
$PASSWORD = 'ваш_новый_пароль';
```

### Изменение директорий
```php
$GLPI_PLUGINS_DIR = '/путь/к/glpi/plugins';
$TEMP_DIR = '/путь/к/временной/директории';
```

### Изменение ограничений
```php
$MAX_FILE_SIZE = 200 * 1024 * 1024; // 200MB
$ALLOWED_EXTENSIONS = ['zip', 'tar', '7z', 'rar'];
```

## Лицензия

Этот скрипт предназначен только для локального использования. Не используйте его в production без дополнительной защиты и аудита безопасности.
