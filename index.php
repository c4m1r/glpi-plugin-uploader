<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GLPI Plugin Uploader</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .auth-form {
            text-align: center;
        }
        .auth-form input[type="password"] {
            padding: 10px;
            font-size: 16px;
            border: 1px solid #ddd;
            border-radius: 4px;
            width: 200px;
        }
        .auth-form button {
            padding: 10px 20px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-left: 10px;
        }
        .auth-form button:hover {
            background: #0056b3;
        }
        .drop-zone {
            border: 2px dashed #007bff;
            border-radius: 8px;
            padding: 40px;
            text-align: center;
            margin: 20px 0;
            transition: border-color 0.3s;
            background: #f8f9fa;
        }
        .drop-zone.dragover {
            border-color: #0056b3;
            background: #e3f2fd;
        }
        .drop-zone.valid {
            border-color: #28a745;
            background: #d4edda;
        }
        .drop-zone.invalid {
            border-color: #dc3545;
            background: #f8d7da;
        }
        .upload-btn {
            background: #28a745;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            margin-top: 10px;
        }
        .upload-btn:hover {
            background: #218838;
        }
        .upload-btn:disabled {
            background: #6c757d;
            cursor: not-allowed;
        }
        .status {
            margin: 20px 0;
            padding: 15px;
            border-radius: 4px;
            white-space: pre-wrap;
            font-family: monospace;
            font-size: 14px;
        }
        .status.success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        .status.error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        .status.info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
        }
        .logout {
            float: right;
            background: #dc3545;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
        }
        .logout:hover {
            background: #c82333;
        }
        .hidden {
            display: none;
        }
        .file-list {
            margin: 10px 0;
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 10px;
            background: #f8f9fa;
        }
        .file-item {
            padding: 5px 0;
            border-bottom: 1px solid #eee;
        }
        .file-item:last-child {
            border-bottom: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php
        session_start();

        // Конфигурация
        $PASSWORD = '123123';
        $GLPI_PLUGINS_DIR = '/var/www/glpi/plugins';
        $TEMP_DIR = '/tmp';
        $MAX_FILE_SIZE = 100 * 1024 * 1024; // 100MB
        $ALLOWED_EXTENSIONS = ['zip', 'tar', '7z'];
        $ALLOWED_MIME_TYPES = [
            'application/zip',
            'application/x-zip-compressed',
            'application/x-tar',
            'application/x-7z-compressed'
        ];

        // Функция для логирования
        function logMessage($message, $type = 'info') {
            $_SESSION['logs'][] = date('H:i:s') . " [$type] $message";
        }

        // Функция для очистки логов
        function clearLogs() {
            $_SESSION['logs'] = [];
        }

        // Функция для получения уникальной директории
        function getUniqueTempDir($prefix = 'glpi_upload_') {
            global $TEMP_DIR;
            $attempts = 0;
            do {
                $dir = $TEMP_DIR . '/' . $prefix . mt_rand(1000, 9999);
                $attempts++;
                if ($attempts > 100) {
                    throw new Exception('Не удалось создать уникальную временную директорию');
                }
            } while (file_exists($dir));
            return $dir;
        }

        // Функция для безопасного удаления директории
        function safeRemoveDir($dir) {
            if (!is_dir($dir)) return;

            $files = array_diff(scandir($dir), ['.', '..']);
            foreach ($files as $file) {
                $path = $dir . '/' . $file;
                if (is_dir($path)) {
                    safeRemoveDir($path);
                } else {
                    unlink($path);
                }
            }
            rmdir($dir);
        }

        // Обработка выхода
        if (isset($_POST['logout'])) {
            session_destroy();
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }

        // Обработка авторизации
        if (isset($_POST['password'])) {
            if ($_POST['password'] === $PASSWORD) {
                $_SESSION['authenticated'] = true;
                clearLogs();
                logMessage('Успешная авторизация');
            } else {
                $auth_error = 'Неверный пароль';
            }
        }

        // Обработка загрузки файла
        if (isset($_SESSION['authenticated']) && $_SESSION['authenticated'] && isset($_FILES['file'])) {
            try {
                clearLogs();
                $file = $_FILES['file'];

                // Проверка ошибок загрузки
                if ($file['error'] !== UPLOAD_ERR_OK) {
                    throw new Exception('Ошибка загрузки файла: ' . $file['error']);
                }

                // Проверка размера файла
                if ($file['size'] > $MAX_FILE_SIZE) {
                    throw new Exception('Файл слишком большой (максимум 100MB)');
                }

                // Проверка расширения
                $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                if (!in_array($extension, $ALLOWED_EXTENSIONS)) {
                    throw new Exception('Недопустимый формат файла. Разрешены: ' . implode(', ', $ALLOWED_EXTENSIONS));
                }

                // Проверка MIME типа
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime_type = finfo_file($finfo, $file['tmp_name']);
                finfo_close($finfo);

                if (!in_array($mime_type, $ALLOWED_MIME_TYPES)) {
                    throw new Exception('Недопустимый тип файла');
                }

                // Создание временной директории
                $temp_dir = getUniqueTempDir();
                if (!mkdir($temp_dir, 0755, true)) {
                    throw new Exception('Не удалось создать временную директорию');
                }

                logMessage('Создана временная директория: ' . $temp_dir);

                // Перемещение файла во временную директорию
                $temp_file = $temp_dir . '/' . basename($file['name']);
                if (!move_uploaded_file($file['tmp_name'], $temp_file)) {
                    throw new Exception('Не удалось сохранить файл');
                }

                logMessage('Файл загружен: ' . basename($file['name']));

                // Распаковка архива
                $extract_dir = $temp_dir . '/extracted';
                if (!mkdir($extract_dir, 0755, true)) {
                    throw new Exception('Не удалось создать директорию для распаковки');
                }

                logMessage('Распаковка архива...');

                switch ($extension) {
                    case 'zip':
                        $zip = new ZipArchive();
                        if ($zip->open($temp_file) === true) {
                            $zip->extractTo($extract_dir);
                            $zip->close();
                            logMessage('ZIP архив распакован');
                        } else {
                            throw new Exception('Не удалось распаковать ZIP архив');
                        }
                        break;

                    case '7z':
                        $command = "7z x '" . escapeshellarg($temp_file) . "' -o'" . escapeshellarg($extract_dir) . "' -y";
                        $output = shell_exec($command . ' 2>&1');
                        if ($output === null) {
                            throw new Exception('Не удалось выполнить распаковку 7z');
                        }
                        logMessage('7Z архив распакован');
                        break;

                    case 'tar':
                        $command = "tar -xf '" . escapeshellarg($temp_file) . "' -C '" . escapeshellarg($extract_dir) . "'";
                        $output = shell_exec($command . ' 2>&1');
                        if ($output === null) {
                            throw new Exception('Не удалось выполнить распаковку tar');
                        }
                        logMessage('TAR архив распакован');
                        break;
                }

                // Определение структуры распакованных файлов
                $extracted_files = array_diff(scandir($extract_dir), ['.', '..']);
                $plugin_name = pathinfo($file['name'], PATHINFO_FILENAME);

                if (count($extracted_files) === 1 && is_dir($extract_dir . '/' . $extracted_files[0])) {
                    // Одна корневая папка - переносим её
                    $source_dir = $extract_dir . '/' . $extracted_files[0];
                    $target_dir = $GLPI_PLUGINS_DIR . '/' . $extracted_files[0];
                } else {
                    // Несколько файлов или нет корневой папки - создаём папку по имени архива
                    $source_dir = $extract_dir;
                    $target_dir = $GLPI_PLUGINS_DIR . '/' . $plugin_name;
                }

                // Копирование в GLPI
                logMessage('Копирование в GLPI plugins...');
                if (is_dir($target_dir)) {
                    // Удаляем существующую директорию
                    safeRemoveDir($target_dir);
                    logMessage('Удалена существующая директория плагина');
                }

                $copy_command = "cp -r '" . escapeshellarg($source_dir) . "' '" . escapeshellarg($target_dir) . "'";
                $output = shell_exec($copy_command . ' 2>&1');
                if ($output !== null && strpos($output, 'error') !== false) {
                    throw new Exception('Ошибка копирования: ' . $output);
                }

                logMessage('Файлы скопированы в GLPI plugins');

                // Установка прав
                logMessage('Установка прав доступа...');

                $perms_command = "sudo /usr/local/bin/glpi_fix_perms.sh";
                $output = shell_exec($perms_command . ' 2>&1');
                if ($output !== null) {
                    logMessage('Результат установки прав: ' . $output);
                }

                logMessage('Права доступа установлены');

                // Очистка временных файлов
                safeRemoveDir($temp_dir);
                logMessage('Временные файлы удалены');

                $_SESSION['success'] = 'Плагин успешно установлен!';

            } catch (Exception $e) {
                logMessage('ОШИБКА: ' . $e->getMessage(), 'error');
                $_SESSION['error'] = $e->getMessage();

                // Очистка в случае ошибки
                if (isset($temp_dir) && is_dir($temp_dir)) {
                    safeRemoveDir($temp_dir);
                }
            }
        }

        // Отображение интерфейса
        if (!isset($_SESSION['authenticated']) || !$_SESSION['authenticated']) {
            // Форма авторизации
            ?>
            <h1>GLPI Plugin Uploader</h1>
            <div class="auth-form">
                <form method="post">
                    <input type="password" name="password" placeholder="Введите пароль" required>
                    <button type="submit">Войти</button>
                </form>
                <?php if (isset($auth_error)): ?>
                    <p style="color: red; margin-top: 10px;"><?php echo htmlspecialchars($auth_error); ?></p>
                <?php endif; ?>
            </div>
            <?php
        } else {
            // Основной интерфейс
            ?>
            <h1>GLPI Plugin Uploader</h1>
            <button class="logout" onclick="document.getElementById('logout-form').submit();">Выход</button>
            <form id="logout-form" method="post" style="display: none;">
                <input type="hidden" name="logout" value="1">
            </form>

            <p>Перетащи сюда файлы для переноса в GLPI и автоматической прописи прав для работы добавляемых Плагинов.</p>

            <div class="drop-zone" id="drop-zone">
                <p>Перетащите архив сюда или нажмите для выбора файла</p>
                <p>Поддерживаемые форматы: .zip, .tar, .7z (макс. 100MB)</p>
                <input type="file" id="file-input" accept=".zip,.tar,.7z" style="display: none;">
                <button type="button" onclick="document.getElementById('file-input').click();">Выбрать файл</button>
            </div>

            <div id="file-info" class="hidden">
                <h3>Выбранный файл:</h3>
                <div id="file-details"></div>
            </div>

            <form id="upload-form" method="post" enctype="multipart/form-data" class="hidden">
                <input type="file" name="file" id="form-file-input" accept=".zip,.tar,.7z">
                <button type="submit" class="upload-btn" id="upload-btn">Загрузить и установить</button>
            </form>

            <?php
            // Отображение статусов
            if (isset($_SESSION['success'])) {
                echo '<div class="status success">' . htmlspecialchars($_SESSION['success']) . '</div>';
                unset($_SESSION['success']);
            }
            if (isset($_SESSION['error'])) {
                echo '<div class="status error">' . htmlspecialchars($_SESSION['error']) . '</div>';
                unset($_SESSION['error']);
            }

            // Отображение логов
            if (isset($_SESSION['logs']) && !empty($_SESSION['logs'])) {
                echo '<div class="status info"><h3>Лог выполнения:</h3>';
                foreach ($_SESSION['logs'] as $log) {
                    echo htmlspecialchars($log) . "\n";
                }
                echo '</div>';
            }
            ?>
            <?php
        }
        ?>
    </div>

    <script>
        const dropZone = document.getElementById('drop-zone');
        const fileInput = document.getElementById('file-input');
        const formFileInput = document.getElementById('form-file-input');
        const uploadForm = document.getElementById('upload-form');
        const fileInfo = document.getElementById('file-info');
        const fileDetails = document.getElementById('file-details');
        const uploadBtn = document.getElementById('upload-btn');

        const allowedExtensions = ['zip', 'tar', '7z'];
        const maxSize = 100 * 1024 * 1024; // 100MB

        // Drag & Drop события
        dropZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropZone.classList.add('dragover');
        });

        dropZone.addEventListener('dragleave', () => {
            dropZone.classList.remove('dragover');
        });

        dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropZone.classList.remove('dragover');

            const files = e.dataTransfer.files;
            if (files.length > 0) {
                handleFile(files[0]);
            }
        });

        // Выбор файла через кнопку
        fileInput.addEventListener('change', (e) => {
            if (e.target.files.length > 0) {
                handleFile(e.target.files[0]);
            }
        });

        function handleFile(file) {
            // Проверка расширения
            const extension = file.name.split('.').pop().toLowerCase();
            if (!allowedExtensions.includes(extension)) {
                showError('Недопустимый формат файла. Разрешены: ' + allowedExtensions.join(', '));
                return;
            }

            // Проверка размера
            if (file.size > maxSize) {
                showError('Файл слишком большой (максимум 100MB)');
                return;
            }

            // Отображение информации о файле
            fileDetails.innerHTML = `
                <div class="file-item">
                    <strong>Имя:</strong> ${file.name}<br>
                    <strong>Размер:</strong> ${(file.size / 1024 / 1024).toFixed(2)} MB<br>
                    <strong>Тип:</strong> ${file.type || 'Не определен'}
                </div>
            `;
            fileInfo.classList.remove('hidden');
            uploadForm.classList.remove('hidden');

            // Копирование файла в форму
            const dataTransfer = new DataTransfer();
            dataTransfer.items.add(file);
            formFileInput.files = dataTransfer.files;

            dropZone.classList.add('valid');
            dropZone.classList.remove('invalid');
            uploadBtn.disabled = false;
        }

        function showError(message) {
            fileDetails.innerHTML = `<div style="color: red;">${message}</div>`;
            fileInfo.classList.remove('hidden');
            uploadForm.classList.add('hidden');
            dropZone.classList.add('invalid');
            dropZone.classList.remove('valid');
            uploadBtn.disabled = true;
        }

        // Валидация перед отправкой формы
        uploadForm.addEventListener('submit', (e) => {
            uploadBtn.disabled = true;
            uploadBtn.textContent = 'Загрузка...';
        });
    </script>
</body>
</html>
