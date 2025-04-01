<?php
include_once 'database.php';
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

$database = new Database();
$conn = $database->getConnection();

$data = json_decode(file_get_contents("php://input"));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action'])) {
    if ($_GET['action'] === 'register') {
        // Registration
        $name = $data->name;
        $email = $data->email;
        $password = password_hash($data->password, PASSWORD_DEFAULT);
        $role = $data->role;

        $query = "INSERT INTO users (name, email, password, role) VALUES (:name, :email, :password, :role)";
        $stmt = $conn->prepare($query);
        $stmt->execute([
            ':name' => $name,
            ':email' => $email,
            ':password' => $password,
            ':role' => $role
        ]);

        echo json_encode(["message" => "User registered successfully"]);
    
    } elseif ($_GET['action'] === 'login') {
        // Login
        $email = $data->email;
        $password = $data->password;

        $query = "SELECT * FROM users WHERE email = :email";
        $stmt = $conn->prepare($query);
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            echo json_encode(["session_id" => session_id(), "user" => $user]);
        } else {
            echo json_encode(["error" => "Invalid email or password"]);
        }
    }
}
?>
