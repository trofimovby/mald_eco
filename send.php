<?php
// send.php — ТОЛЬКО U-ON CRM + Лог в файл

// =========================================================
// НАСТРОЙКИ
// =========================================================
$uonApiKey = "2F37vTS79bVplWoa1bAM1767889699"; // Твой ключ U-ON
// =========================================================

header('Content-Type: application/json');

// 1. ПОЛУЧАЕМ ДАННЫЕ ИЗ ФОРМЫ
$name = $_POST['name'] ?? 'Клиент с сайта';
$rawPhone = $_POST['phone'] ?? '';
$contactMethod = $_POST['contact_method_type'] ?? 'phone';
$messengerApp = $_POST['messenger_app'] ?? '';
$messengerContact = $_POST['messenger_contact'] ?? '';
$source = $_POST['source'] ?? 'Лендинг Мальдивы';

// Очистка телефона (оставляем только цифры)
$cleanPhone = preg_replace('/[^0-9]/', '', $rawPhone);
// Если телефона нет, но есть контакт мессенджера (и там цифры), пробуем использовать его
if (empty($cleanPhone) && !empty($messengerContact)) {
    $cleanPhone = preg_replace('/[^0-9]/', '', $messengerContact);
}

// 2. ФОРМИРУЕМ ПРИМЕЧАНИЕ (Как в твоем старом скрипте)
$noteText = "Источник: $source.";
if ($contactMethod === 'messenger') {
    $noteText .= " Хочет общаться в " . ucfirst($messengerApp) . ". Контакт: $messengerContact";
} else {
    $noteText .= " Просит перезвонить.";
}

// 3. ОТПРАВКА В U-ON CRM
$crmStatus = "Не отправлено (короткий номер)";
$responseBody = "";

// Проверка длины номера (как в твоем JS: > 6 цифр)
if (strlen($cleanPhone) > 6) {

    $url = "https://api.u-on.ru/{$uonApiKey}/request/create.json";

    $data = [
        'u_name'      => $name,
        'u_phone'     => $cleanPhone,
        'u_note'      => $noteText,    // Примечание к туристу
        'r_note'      => $noteText,    // Примечание к заявке
        'r_source'    => 'Сайт',       // Источник
        'r_status_id' => '1'           // Статус "Новая"
    ];

    // Инициализация cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    // Выполнение запроса
    $responseBody = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode == 200) {
        $crmStatus = "Успешно (ID заявки есть в ответе)";
    } else {
        $crmStatus = "Ошибка U-ON: $responseBody";
    }
}

$file = 'leads.csv';
$date = date('d.m.Y H:i:s');
$logEntry = "$date | $name | $cleanPhone | $noteText | $crmStatus\n";

// Проверяем: если файла ещё нет, создаем его и добавляем BOM
// BOM — это \xEF\xBB\xBF, метка, которая говорит Excel'ю: "Тут UTF-8, покажи русские буквы правильно"
if (!file_exists($file)) {
    file_put_contents($file, "\xEF\xBB\xBF");
}

// Дописываем новую заявку
file_put_contents($file, $logEntry, FILE_APPEND);

// 5. ОТВЕТ САЙТУ
echo json_encode([
    'status' => 'success',
    'crm_response' => $crmStatus
]);
?>