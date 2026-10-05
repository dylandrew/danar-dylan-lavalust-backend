<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class ProductController extends Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->call->library('api');
        $this->call->database();
    }

    public function index()
    {
        $this->authenticate();
        $statement = $this->db->raw(
            'SELECT id, product_name, description, price, quantity, created_at FROM products ORDER BY created_at DESC, id DESC'
        );
        $this->api->respond(['data' => $statement->fetchAll(PDO::FETCH_ASSOC)]);
    }

    public function create()
    {
        $this->authenticate();
        $input = $this->validated_fields($this->request_body());

        $this->db->raw(
            'INSERT INTO products (product_name, description, price, quantity) VALUES (?, ?, ?, ?)',
            [$input['product_name'], $input['description'], $input['price'], $input['quantity']]
        );

        $this->api->respond(['data' => $this->find_product($this->db->last_id())], 201);
    }

    public function update($id)
    {
        $this->authenticate();
        $id = $this->validated_id($id);
        $input = $this->request_body();
        $partial = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'PUT') === 'PATCH';
        $fields = $this->validated_fields($input, $partial);

        if (!$fields) {
            $this->api->respond_error('Provide at least one product field to update.', 422);
        }

        $assignments = [];
        $values = [];
        foreach ($fields as $column => $value) {
            $assignments[] = "`{$column}` = ?";
            $values[] = $value;
        }
        $values[] = $id;
        $this->db->raw('UPDATE products SET ' . implode(', ', $assignments) . ' WHERE id = ?', $values);

        $product = $this->find_product($id);
        if (!$product) {
            $this->api->respond_error('Product not found.', 404);
        }

        $this->api->respond(['data' => $product]);
    }

    public function delete($id)
    {
        $this->authenticate();
        $id = $this->validated_id($id);
        $statement = $this->db->raw('DELETE FROM products WHERE id = ?', [$id]);

        if ($statement->rowCount() === 0) {
            $this->api->respond_error('Product not found.', 404);
        }

        $this->api->respond(['message' => 'Product deleted successfully.']);
    }

    private function authenticate()
    {
        $identity = $this->api->require_jwt();
        $user = $this->db->raw(
            'SELECT id FROM users WHERE id = ? AND is_active = 1 LIMIT 1',
            [$identity['sub']]
        )->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $this->api->respond_error('Unauthorized', 401);
        }
    }

    private function validated_fields(array $input, $partial = false)
    {
        $fields = [];

        if (!$partial || array_key_exists('product_name', $input)) {
            $name = trim((string) ($input['product_name'] ?? ''));
            if ($name === '' || strlen($name) > 100) {
                $this->api->respond_error('Product name is required and must be at most 100 characters.', 422);
            }
            $fields['product_name'] = $name;
        }

        if (!$partial || array_key_exists('description', $input)) {
            $description = (string) ($input['description'] ?? '');
            if (strlen($description) > 60000) {
                $this->api->respond_error('Description is too long.', 422);
            }
            $fields['description'] = $description;
        }

        if (!$partial || array_key_exists('price', $input)) {
            $price = (string) ($input['price'] ?? '');
            if (!preg_match('/^(?:\d{1,8})(?:\.\d{1,2})?$/', $price)) {
                $this->api->respond_error('Price must be a non-negative amount with up to two decimal places.', 422);
            }
            $fields['price'] = $price;
        }

        if (!$partial || array_key_exists('quantity', $input)) {
            $quantity = filter_var($input['quantity'] ?? null, FILTER_VALIDATE_INT);
            if ($quantity === false || $quantity < 0) {
                $this->api->respond_error('Quantity must be a non-negative whole number.', 422);
            }
            $fields['quantity'] = $quantity;
        }

        return $fields;
    }

    private function validated_id($id)
    {
        $id = filter_var($id, FILTER_VALIDATE_INT);
        if ($id === false || $id < 1) {
            $this->api->respond_error('Invalid product ID.', 422);
        }
        return $id;
    }

    private function find_product($id)
    {
        return $this->db->raw(
            'SELECT id, product_name, description, price, quantity, created_at FROM products WHERE id = ? LIMIT 1',
            [$id]
        )->fetch(PDO::FETCH_ASSOC);
    }

    private function request_body()
    {
        $input = $this->request->json();
        if (!is_array($input)) {
            $this->api->respond_error('Request body must be valid JSON.', 400);
        }
        return $input;
    }
}