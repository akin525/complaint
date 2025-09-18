<?php

namespace Database\Seeders;

use App\Models\ComplaintStatus;
use Illuminate\Database\Seeder;

class ComplaintStatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $statuses = [
            [
                'name' => 'New',
                'description' => 'Complaint has been submitted but not yet reviewed',
                'color' => '#3498db', // Blue
                'is_active' => true,
            ],
            [
                'name' => 'Under Review',
                'description' => 'Complaint is being reviewed by the staff',
                'color' => '#f39c12', // Orange
                'is_active' => true,
            ],
            [
                'name' => 'In Progress',
                'description' => 'Complaint is being addressed by the staff',
                'color' => '#9b59b6', // Purple
                'is_active' => true,
            ],
            [
                'name' => 'On Hold',
                'description' => 'Complaint resolution is temporarily paused',
                'color' => '#e74c3c', // Red
                'is_active' => true,
            ],
            [
                'name' => 'Resolved',
                'description' => 'Complaint has been resolved',
                'color' => '#2ecc71', // Green
                'is_active' => true,
            ],
            [
                'name' => 'Closed',
                'description' => 'Complaint has been closed without resolution',
                'color' => '#7f8c8d', // Gray
                'is_active' => true,
            ],
            [
                'name' => 'Reopened',
                'description' => 'Previously resolved complaint has been reopened',
                'color' => '#e67e22', // Dark Orange
                'is_active' => true,
            ],
        ];

        foreach ($statuses as $status) {
            ComplaintStatus::create($status);
        }
    }
}