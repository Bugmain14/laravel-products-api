<?php

namespace Database\Factories;

use App\Models\Currency;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    public function definition(): array
    {
        $product = fake()->randomElement([
            ['Laptop Lenovo ThinkPad', 'Portátil empresarial de 14 pulgadas con 16 GB de RAM.'],
            ['Mouse inalámbrico Logitech', 'Mouse ergonómico con conexión Bluetooth.'],
            ['Teclado mecánico Redragon', 'Teclado mecánico con iluminación RGB.'],
            ['Monitor Samsung 24"', 'Monitor Full HD de 24 pulgadas.'],
            ['Audífonos Sony', 'Audífonos inalámbricos con cancelación de ruido.'],
            ['Impresora Epson EcoTank', 'Impresora multifuncional con tanque de tinta.'],
            ['Disco SSD Kingston 1 TB', 'Unidad de estado sólido NVMe de 1 TB.'],
            ['Silla ergonómica', 'Silla de oficina con soporte lumbar ajustable.'],
            ['Cámara web Logitech C920', 'Cámara web Full HD para videollamadas.'],
            ['Router TP-Link', 'Router WiFi 6 de doble banda.'],
        ]);

        $price = fake()->randomFloat(2, 20, 1500);

        return [
            'name' => $product[0],
            'description' => $product[1],
            'price' => $price,
            'currency_id' => Currency::factory(),
            'tax_cost' => round($price * 0.19, 2),
            'manufacturing_cost' => round($price * fake()->randomFloat(2, 0.4, 0.7), 2),
        ];
    }
}