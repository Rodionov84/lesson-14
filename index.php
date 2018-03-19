<?php
@session_start();
include("connect.php");

if( !isset( $_SESSION["auth"] ) )
{
    include("auth.php");
    exit();
}

$action = isset($_GET["action"]) ? $_GET["action"] : "";
$id = isset($_GET["id"]) ? intval($_GET["id"])  : 0;
$sort = isset($_POST["sort"]) ? $_POST["sort"] : "";
$is_done = isset($_GET['is_done']) ? $_GET['is_done'] : 0;
$description = "";

if( $action == "exit" )
{
    unset( $_SESSION["auth"] );

    $msg = "Сессия окончена.";

    include("auth.php");
    exit();
}
else if( $action == "done" )
{
    $db->query("UPDATE `tasks2` SET `is_done`=1 WHERE `id` = " . $id);

    header("Location: " . $_SERVER["SCRIPT_NAME"]);
    exit();
}
else if( $action == "remove" )
{
    $db->query("DELETE FROM `tasks2` WHERE `id` = " . $id);

    header("Location: " . $_SERVER["SCRIPT_NAME"]);
    exit();
}
else if( $action == "edit" )
{
    //$is_done = $db->query("SELECT `is_done` FROM `tasks` WHERE `id` = " . $id)->fetch();
    //$is_done = $is_done['is_done'];
    $description = $db->query("SELECT `description`, `is_done` FROM `tasks2` WHERE `id` = " . $id)->fetch();
    $is_done = $description["is_done"];
    $description = $description["description"];
}

if( isset( $_POST["set_responsible"] ) )
{
    $id = intval($_POST["id"]);
    $user_id = intval($_POST["user_id"]);
    if( $user_id ) {
        $db->query("UPDATE `tasks2` SET `responsible`=$user_id WHERE `id` = $id AND `author` = " . $_SESSION["auth"]);
    }
}
else if( isset( $_POST["id"] ) )
{
    $id = intval($_POST["id"]);
    $description = $_POST["description"];

    if( $id == 0 )
    {
        $db->query("INSERT INTO `tasks2`(`description`, `date_added`, `author`) VALUES ('$description', now(), " . $_SESSION["auth"] . ")");
    }
    else
    {
        $is_done = intval($_POST['is_done']);

        $db->query("UPDATE `tasks2` SET `description`='$description', `is_done`='$is_done' WHERE `id` = " . $id);

        header("Location: " . $_SERVER["SCRIPT_NAME"]);
        exit();
    }
}
?>
<!doctype html>
<html>
    <head>
        <meta charset="utf-8">
        <link rel="stylesheet" type="text/css" href="style.css">
        <title>4.2</title>
    </head>

    <body>
        <form method="post">
            <input type="hidden" name="id" value="<?php echo $id; ?>">
            <input type="text" name="description" placeholder="Описание" value="<?php echo $description; ?>">
            <?php if($id) { ?>
                <select name="is_done">
                    <option value="0" <?php echo !$is_done ? " selected" : "";?>>В процессе</option>
                    <option value="1" <?php echo $is_done ? " selected" : "";?>>Готово</option>            <!-- условие что при редактировании выводить select-->
                </select>                                                                                                    <!-- edit-->
            <?php } ?>
            <input type="submit" value="<?php echo $id ? "Редактировать" : "Добавить"; ?>">
        </form>
        <form method="post">
            <label for="sort">Сортировать по:</label>
            <select name="sort">
                <option value="date_added"<?php echo $sort == "date_added" ? " selected" : ""; ?>>Дате добавления</option>
                <option value="is_done"<?php echo $sort == "is_done" ? " selected" : ""; ?>>Статусу</option>
                <option value="description"<?php echo $sort == "description" ? " selected" : ""; ?>>Описанию</option>
            </select>
            <input type="submit" value="Отсортировать">
        </form>
        <br><br>
        <table>
            <thead>
            <tr>
                <th>Описание задачи</th>
                <th>Дата добавления</th>
                <th>Статус</th>
                <th></th>
                <th>Ответственный</th>
                <th>Автор</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
    <?php
    $sort = $sort != "" ? " ORDER BY `" . $sort . "`" : "";

    $sql = "SELECT `tasks2`.*, `users_author`.`login` AS `author_login`, `users_responsible`.`login` AS `responsible_login`
FROM `tasks2`
LEFT JOIN `users` AS `users_author` ON `users_author`.`id` = `tasks2`.`author`
LEFT JOIN `users` AS `users_responsible` ON `users_responsible`.`id` = `tasks2`.`responsible`
WHERE `tasks2`.`author` = " . $_SESSION["auth"] . $sort;
    $query = $db->query($sql);

    if( $query->rowCount() ) {
        $users_query = $db->query("SELECT `id`, `login` FROM `users`");
        $users = "<option disabled selected>Ответственный</option>";
        if( $users_query->rowCount() )
        {
            foreach ($users_query as $user)
            {
                if( $user["id"] != $_SESSION["auth"] ) {
                    $users .= '<option value="' . $user["id"] . '">' . $user["login"] . '</option>';
                }
            }
        }

        foreach ($query as $row) {
            printf("<tr><td>%s</td>
                                    <td>%s</td><td class='%s'>%s</td>
                                    <td><a href='%s?action=edit&id=%d'>Изменить</a> 
                                           %s
                                           <a href='%s?action=remove&id=%d'>Удалить</a></td>
                                           <td>%s</td>
                                    <td>%s</td>
                                    <td><form method='post'><input type='hidden' name='id' value='%d'><select name='user_id' required>%s</select><input type='submit' name='set_responsible' value='Назначить'></form></td></tr>",
                $row["description"],
                $row["date_added"],
                $row["is_done"] ? "done" : "inProcess",
                $row["is_done"] ? "Выполнено" : "В процессе",
                $_SERVER['SCRIPT_NAME'],
                $row["id"],
                // ниже дополнить условие
                $row["is_done"] ? "" : "<a href='" . $_SERVER['SCRIPT_NAME'] . "?action=done&id=" . $row["id"] . "''>Выполнить</a>",
                $_SERVER['SCRIPT_NAME'],
                $row["id"],
                $row["responsible"] ? $row["responsible_login"] : "Вы",
                $row["author_login"],
                $row["id"],
                $users
            );
        }
    }

     ?>
            </tbody>
        </table>

        <h3>Также, посмотрите, что от Вас требуют другие люди:</h3>
        <table>
            <thead>
                <tr>
                    <th>Описание задачи</th>
                    <th>Дата добавления</th>
                    <th>Статус</th>
                    <th></th>
                    <th>Ответственный</th>
                    <th>Автор</th>
                </tr>
            </thead>
            <tbody>
            <?php
    $sort = $sort != "" ? " ORDER BY `" . $sort . "`" : "";

    $sql = "SELECT `tasks2`.*, `users_author`.`login` AS `author_login`, `users_responsible`.`login` AS `responsible_login`
FROM `tasks2`
LEFT JOIN `users` AS `users_author` ON `users_author`.`id` = `tasks2`.`author`
LEFT JOIN `users` AS `users_responsible` ON `users_responsible`.`id` = `tasks2`.`responsible`
WHERE `tasks2`.`responsible` = " . $_SESSION["auth"] . $sort;
    $query = $db->query($sql);

    if( $query->rowCount() ) {
        foreach ($query as $row) {
            printf("<tr><td>%s</td>
                                    <td>%s</td><td class='%s'>%s</td>
                                    <td><a href='%s?action=edit&id=%d'>Изменить</a> 
                                           %s
                                           <a href='%s?action=remove&id=%d'>Удалить</a></td>
                                           <td>%s</td>
                                    <td>%s</td></tr>",
                $row["description"],
                $row["date_added"],
                $row["is_done"] ? "done" : "inProcess",
                $row["is_done"] ? "Выполнено" : "В процессе",
                $_SERVER['SCRIPT_NAME'],
                $row["id"],
                // ниже дополнить условие
                $row["is_done"] ? "" : "<a href='" . $_SERVER['SCRIPT_NAME'] . "?action=done&id=" . $row["id"] . "''>Выполнить</a>",
                $_SERVER['SCRIPT_NAME'],
                $row["id"],
                $row["responsible"] ? $row["responsible_login"] : "Вы",
                $row["author_login"]
            );
        }
    }
    ?>
            </tbody>
        </table>

        <a href="<?php echo $_SERVER['SCRIPT_NAME'];?>?action=exit">Выход</a>
    </body>
</html>