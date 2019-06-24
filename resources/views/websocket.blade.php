<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>WebSocket Client</title>

    <script type="text/javascript">

        var conn = new WebSocket('ws://localhost:6001');
        conn.onopen = function(e) {
            console.log("Connection established!");
        };

    </script>

</head>
<body>
<table>
    <tr>
        <td> <label id="rateLbl">Current Rate:</label></td>
        <td> <label id="rate">0</label></td>
    </tr>
</table>
</body>
</html>