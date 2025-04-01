<?php
include_once 'database.php';
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

$database = new Database();
$conn = $database->getConnection();
$data = json_decode(file_get_contents("php://input"));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action'])) {
    if ($_GET['action'] === 'place_bid') {
        $product_id = $data->product_id;
        $amount = $data->amount;
        $user_id = $data->user_id;

        $query = "INSERT INTO bids (product_id, user_id, amount) VALUES (:product_id, :user_id, :amount)";
        $stmt = $conn->prepare($query);
        $stmt->execute([
            ':product_id' => $product_id,
            ':user_id' => $user_id,
            ':amount' => $amount
        ]);

        echo json_encode(["message" => "Bid placed successfully"]);
    }
}
?>
