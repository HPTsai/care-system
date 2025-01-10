<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>檔案上傳</title>
</head>
<body>
    <h1>檔案上傳</h1>

    @if(session('success'))
        <p style="color: green;">{{ session('success') }}</p>
        <p>檔案儲存路徑: {{ session('path') }}</p>
    @endif

    <form action="" method="POST" enctype="multipart/form-data">
        @csrf
        <label for="file">選擇檔案：</label>
        <input type="file" name="file" id="file" required>
        <button type="submit">上傳</button>
    </form>
</body>
</html>