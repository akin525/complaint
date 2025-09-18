<?php

namespace Tests\Feature;

use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\ComplaintStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;
    protected $staff;
    protected $student;
    protected $category;
    protected $status;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test users
        $this->admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $this->staff = User::factory()->create([
            'role' => 'staff',
            'department' => 'IT Department',
        ]);

        $this->student = User::factory()->create([
            'role' => 'student',
            'student_id' => 'STU123',
            'department' => 'Computer Science',
        ]);

        // Create a test category
        $this->category = ComplaintCategory::create([
            'name' => 'Test Category',
            'description' => 'Test Category Description',
            'is_active' => true,
        ]);

        // Create a test status
        $this->status = ComplaintStatus::create([
            'name' => 'New',
            'description' => 'New complaint',
            'color' => '#3498db',
            'is_active' => true,
        ]);

        // Create some complaints
        Complaint::create([
            'user_id' => $this->student->id,
            'category_id' => $this->category->id,
            'status_id' => $this->status->id,
            'subject' => 'Test Complaint 1',
            'description' => 'This is test complaint 1',
            'is_anonymous' => false,
            'is_resolved' => false,
        ]);

        Complaint::create([
            'user_id' => $this->student->id,
            'category_id' => $this->category->id,
            'status_id' => $this->status->id,
            'subject' => 'Test Complaint 2',
            'description' => 'This is test complaint 2',
            'is_anonymous' => true,
            'is_resolved' => false,
        ]);
    }

    /**
     * Test admin can access dashboard.
     */
    public function test_admin_can_access_dashboard(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/admin/dashboard');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'total_complaints',
                    'resolved_complaints',
                    'pending_complaints',
                    'resolution_rate',
                    'complaints_by_category',
                    'complaints_by_status',
                    'users_by_role',
                    'recent_complaints',
                ],
            ]);
    }

    /**
     * Test non-admin cannot access dashboard.
     */
    public function test_non_admin_cannot_access_dashboard(): void
    {
        $response = $this->actingAs($this->student)
            ->getJson('/api/admin/dashboard');

        $response->assertStatus(403);
    }

    /**
     * Test admin can list all users.
     */
    public function test_admin_can_list_all_users(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/admin/users');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'name',
                            'email',
                            'role',
                        ],
                    ],
                ],
            ]);

        // Check that the response contains all users
        $response->assertJsonFragment([
            'email' => $this->admin->email,
        ]);

        $response->assertJsonFragment([
            'email' => $this->staff->email,
        ]);

        $response->assertJsonFragment([
            'email' => $this->student->email,
        ]);
    }

    /**
     * Test admin can create a new user.
     */
    public function test_admin_can_create_user(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/admin/users', [
                'name' => 'New User',
                'email' => 'newuser@example.com',
                'password' => 'password',
                'role' => 'student',
                'student_id' => 'STU456',
                'department' => 'Mathematics',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'id',
                    'name',
                    'email',
                    'role',
                    'student_id',
                    'department',
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'newuser@example.com',
            'role' => 'student',
            'student_id' => 'STU456',
            'department' => 'Mathematics',
        ]);
    }

    /**
     * Test admin can update a user.
     */
    public function test_admin_can_update_user(): void
    {
        $response = $this->actingAs($this->admin)
            ->putJson('/api/admin/users/' . $this->student->id, [
                'name' => 'Updated Student Name',
                'department' => 'Physics',
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data',
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $this->student->id,
            'name' => 'Updated Student Name',
            'department' => 'Physics',
        ]);
    }

    /**
     * Test admin can delete a user.
     */
    public function test_admin_can_delete_user(): void
    {
        $newUser = User::factory()->create([
            'role' => 'student',
        ]);

        $response = $this->actingAs($this->admin)
            ->deleteJson('/api/admin/users/' . $newUser->id);

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'message' => 'User deleted successfully',
            ]);

        $this->assertDatabaseMissing('users', [
            'id' => $newUser->id,
        ]);
    }

    /**
     * Test admin can create a category.
     */
    public function test_admin_can_create_category(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/admin/categories', [
                'name' => 'New Category',
                'description' => 'New Category Description',
                'is_active' => true,
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'id',
                    'name',
                    'description',
                    'is_active',
                ],
            ]);

        $this->assertDatabaseHas('complaint_categories', [
            'name' => 'New Category',
            'description' => 'New Category Description',
        ]);
    }

    /**
     * Test admin can create a status.
     */
    public function test_admin_can_create_status(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/admin/statuses', [
                'name' => 'New Status',
                'description' => 'New Status Description',
                'color' => '#e74c3c',
                'is_active' => true,
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'id',
                    'name',
                    'description',
                    'color',
                    'is_active',
                ],
            ]);

        $this->assertDatabaseHas('complaint_statuses', [
            'name' => 'New Status',
            'description' => 'New Status Description',
            'color' => '#e74c3c',
        ]);
    }

    /**
     * Test admin can generate reports.
     */
    public function test_admin_can_generate_reports(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/admin/reports?report_type=complaints');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'report_type',
                'date_from',
                'date_to',
                'data',
            ]);
    }
}