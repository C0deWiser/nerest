# NeReSt — Next Remote Storage

Сервер и `flysystem`-адаптер для хранения файлов на удаленном сервере. 

## Авторизация запросов

Входящие запросы авторизуются заголовком `Authorization` с `Bearer` токеном, создаваемым функцией:

```php
hash_hmac('sha256', $path, $secret);
```

Где, 

* `path` — путь запрашиваемого ресурса;
* `secret` — значение переменной `NEREST_SECRET`;

## Стандартные ответы

Любой запрос может вернуть:

* `400 Bad Request`, если нераспознан или не содержит необходимых полей.
* `401 Unauthorized`, если не прошел авторизацию.
* `500 Server Error`, если...

## GET

### Получение файла

```http
GET path/to/file.txt HTTP/1.1
Host: nerest.example.com
Authorization: Bearer ********
```

Ответы: 

* `202 OK` с `StreamedResponse` содержимого файла.
* `404 Not Found`

### Наличие файла

```http
GET path/to/file.txt HTTP/1.1
Host: nerest.example.com
Authorization: Bearer ********

?fileExists
```

Ответы:

* `202 OK`
    ```http
    HTTP/1.1 200 OK
    Content-Type: application/json
  
    true
    ```

### Наличие папки

```http
GET path/to HTTP/1.1
Host: nerest.example.com
Authorization: Bearer ********

?directoryExists
```

Ответы:

* `202 OK`
    ```http
    HTTP/1.1 200 OK
    Content-Type: application/json
  
    false
    ```

### Checksum файла

```http
GET path/to/file.txt HTTP/1.1
Host: nerest.example.com
Authorization: Bearer ********

?checksum
```

Ответы:

* `202 OK`
    ```http
    HTTP/1.1 200 OK
    Content-Type: application/json
  
    "c06005bad483b77d821a2c23ec6fb8ad"
    ```
* `404 Not Found`

### Метаданные

```http
GET path/to/file.txt HTTP/1.1
Host: nerest.example.com
Authorization: Bearer ********

?metadata
```

Ответы:

* `202 OK`
    ```http
    HTTP/1.1 200 OK
    Content-Type: application/json
  
    {
        "type": "file",
        "path": "path/to/file.txt",
        "file_size": 624,
        "visibility": "private",
        "last_modified": 1682671163,
        "mime_type": "text/plain"
    }
    ```

    ```http
    HTTP/1.1 200 OK
    Content-Type: application/json
  
    {
        "type": "dir",
        "path": "path/to",
        "visibility": "public",
        "last_modified": 1682671163
    }
    ```
* `404 Not Found`

### Содержимое файла

```http
GET path/to/file.txt HTTP/1.1
Host: nerest.example.com
Authorization: Bearer ********

?contents
```

Ответы:

* `202 OK` с содержимым файла в теле ответа.
* `404 Not Found`

### Листинг папки

```http
GET path/to HTTP/1.1
Host: nerest.example.com
Authorization: Bearer ********

?list=false
```

Если аргумент `list=true`, то будет осуществлен рекурсивный листинг. Ответ в любом случае будет плоским массивом.

Допустим запрос без пути, то есть к корневой папке.

Ответы:

* `202 OK` с `json`-массивом элементов типа `metadata` (см. выше)
* `404 Not Found`

## POST

### Создание (замена) файла

```http
POST path/to/file.txt HTTP/1.1
Host: nerest.example.com
Authorization: Bearer ********

?contents=file binary contents
```

Ответы:

* `201 Created`

### Создание папки

```http
POST path/to HTTP/1.1
Host: nerest.example.com
Authorization: Bearer ********

?dir
```

Ответы:

* `201 Created`

## PUT

### Добавление к файлу

```http
PUT path/to/file.txt HTTP/1.1
Host: nerest.example.com
Authorization: Bearer ********

?contents=file binary contents
```

Ответы:

* `204 No Content`
* `404 Not Found`

### Изменение видимости

```http
PUT path/to/file.txt HTTP/1.1
Host: nerest.example.com
Authorization: Bearer ********

?visibility=public
```

Аргумент `visibility` может принимать значения `private` или `public`.

Файл с видимостью `public` все равно не станет доступен без авторизации.

Ответы:

* `204 No Content`
* `404 Not Found`

### Копирование файла

```http
PUT path/to/file.txt HTTP/1.1
Host: nerest.example.com
Authorization: Bearer ********

?copy=path/to/copy.txt
```

Ответы:

* `204 No Content`
* `404 Not Found`

### Перемещение файла

```http
PUT path/to/file.txt HTTP/1.1
Host: nerest.example.com
Authorization: Bearer ********

?move=new/path/to/file.txt
```

Ответы:

* `204 No Content`
* `404 Not Found`

## DELETE

### Удаление файла или папки

```http
DELETE path/to/resource HTTP/1.1
Host: nerest.example.com
Authorization: Bearer ********
```

Ответы:

* `204 No Content`
* `404 Not Found`
