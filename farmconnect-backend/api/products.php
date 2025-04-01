<?php
include_once 'database.php';
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

$database = new Database();
$conn = $database->getConnection();
$data = json_decode(file_get_contents("php://input"));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action'])) {
    if ($_GET['action'] === 'add') {
        $name = $data->name;
        $price = $data->price;
        $stock = $data->stock;
        $farmer_id = $data->farmer_id;

        $query = "INSERT INTO products (name, price, stock, farmer_id) VALUES (:name, :price, :stock, :farmer_id)";
        $stmt = $conn->prepare($query);
        $stmt->execute([
            ':name' => $name,
            ':price' => $price,
            ':stock' => $stock,
            ':farmer_id' => $farmer_id
        ]);

        echo json_encode(["message" => "Product added successfully"]);
    }
}
?>
