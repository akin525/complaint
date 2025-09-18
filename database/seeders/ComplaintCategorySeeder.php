<?php

namespace Database\Seeders;

use App\Models\ComplaintCategory;
use Illuminate\Database\Seeder;

class ComplaintCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Academic Issues',
                'description' => 'Issues related to courses, exams, grades, and academic policies',
                'is_active' => true,
            ],
            [
                'name' => 'Administrative Issues',
                'description' => 'Issues related to administrative procedures, documentation, and services',
                'is_active' => true,
            ],
            [
                'name' => 'Facility Issues',
                'description' => 'Issues related to campus facilities, classrooms, laboratories, and infrastructure',
                'is_active' => true,
            ],
            [
                'name' => 'Financial Issues',
                'description' => 'Issues related to fees, scholarships, financial aid, and payments',
                'is_active' => true,
            ],
            [
                'name' => 'Harassment or Discrimination',
                'description' => 'Issues related to harassment, discrimination, or unfair treatment',
                'is_active' => true,
            ],
            [
                'name' => 'IT Services',
                'description' => 'Issues related to IT infrastructure, internet, software, and technical support',
                'is_active' => true,
            ],
            [
                'name' => 'Library Services',
                'description' => 'Issues related to library resources, access, and services',
                'is_active' => true,
            ],
            [
                'name' => 'Hostel/Accommodation',
                'description' => 'Issues related to student housing and accommodation facilities',
                'is_active' => true,
            ],
            [
                'name' => 'Transportation',
                'description' => 'Issues related to campus transportation and parking',
                'is_active' => true,
            ],
            [
                'name' => 'Other',
                'description' => 'Any other issues not covered by the above categories',
                'is_active' => true,
            ],
        ];

        foreach ($categories as $category) {
            ComplaintCategory::create($category);
        }
    }
}