<?php

namespace Tests\Feature;

use App\Models\Complaint;
use App\Models\ComplaintCategory;
use App\Models\ComplaintStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ComplaintTest extends TestCase
{
    use RefreshDatabase;

    protected $student;
    protected $admin;
    protected $category;
    protected $status;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test users
        $this->student = User::factory()->create([
            'role' => 'student',
            'student_id' => 'STU123',
            'department' => 'Computer Science',
        ]);

        $this->admin = User::factory()->create([
            'role' => 'admin',
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
    }

    /**
     * Test student can create a complaint.
     */
    public function test_student_can_create_complaint(): void
    {
        $response = $this->actingAs($this->student)
            ->postJson('/api/complaints', [
                'category_id' => $this->category->id,
                'subject' => 'Test Complaint',
                'description' => 'This is a test complaint description',
                'is_anonymous' => false,
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'id',
                    'user_id',
                    'category_id',
                    'status_id',
                    'subject',
                    'description',
                    'is_anonymous',
                    'is_resolved',
                    'created_at',
                    'updated_at',
                    'category',
                    'status',
                ],
            ]);

        $this->assertDatabaseHas('complaints', [
            'user_id' => $this->student->id,
            'category_id' => $this->category->id,
            'subject' => 'Test Complaint',
            'description' => 'This is a test complaint description',
        ]);
    }

    /**
     * Test student can view their own complaints.
     */
    public function test_student_can_view_own_complaints(): void
    {
        // Create a complaint for the student
        $complaint = Complaint::create([
            'user_id' => $this->student->id,
            'category_id' => $this->category->id,
            'status_id' => $this->status->id,
            'subject' => 'Test Complaint',
            'description' => 'This is a test complaint description',
            'is_anonymous' => false,
            'is_resolved' => false,
        ]);

        $response = $this->actingAs($this->student)
            ->getJson('/api/complaints');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'user_id',
                            'category_id',
                            'status_id',
                            'subject',
                            'description',
                            'category',
                            'status',
                        ],
                    ],
                ],
            ]);

        // Check that the response contains the student's complaint
        $response->assertJsonFragment([
            'id' => $complaint->id,
            'subject' => 'Test Complaint',
        ]);
    }

    /**
     * Test student cannot view other students' complaints.
     */
    public function test_student_cannot_view_other_students_complaints(): void
    {
        // Create another student
        $otherStudent = User::factory()->create([
            'role' => 'student',
        ]);

        // Create a complaint for the other student
        $complaint = Complaint::create([
            'user_id' => $otherStudent->id,
            'category_id' => $this->category->id,
            'status_id' => $this->status->id,
            'subject' => 'Other Student Complaint',
            'description' => 'This is another student\'s complaint',
            'is_anonymous' => false,
            'is_resolved' => false,
        ]);

        // Try to view the specific complaint as the first student
        $response = $this->actingAs($this->student)
            ->getJson('/api/complaints/' . $complaint->id);

        $response->assertStatus(403);
    }

    /**
     * Test admin can view all complaints.
     */
    public function test_admin_can_view_all_complaints(): void
    {
        // Create complaints for different users
        $studentComplaint = Complaint::create([
            'user_id' => $this->student->id,
            'category_id' => $this->category->id,
            'status_id' => $this->status->id,
            'subject' => 'Student Complaint',
            'description' => 'This is a student complaint',
            'is_anonymous' => false,
            'is_resolved' => false,
        ]);

        // Create another student
        $otherStudent = User::factory()->create([
            'role' => 'student',
        ]);

        $otherComplaint = Complaint::create([
            'user_id' => $otherStudent->id,
            'category_id' => $this->category->id,
            'status_id' => $this->status->id,
            'subject' => 'Other Student Complaint',
            'description' => 'This is another student\'s complaint',
            'is_anonymous' => false,
            'is_resolved' => false,
        ]);

        // Admin should see all complaints
        $response = $this->actingAs($this->admin)
            ->getJson('/api/complaints');

        $response->assertStatus(200);

        // Check that the response contains both complaints
        $response->assertJsonFragment([
            'subject' => 'Student Complaint',
        ]);

        $response->assertJsonFragment([
            'subject' => 'Other Student Complaint',
        ]);
    }

    /**
     * Test admin can update complaint status.
     */
    public function test_admin_can_update_complaint_status(): void
    {
        // Create a complaint
        $complaint = Complaint::create([
            'user_id' => $this->student->id,
            'category_id' => $this->category->id,
            'status_id' => $this->status->id,
            'subject' => 'Test Complaint',
            'description' => 'This is a test complaint description',
            'is_anonymous' => false,
            'is_resolved' => false,
        ]);

        // Create a "Resolved" status
        $resolvedStatus = ComplaintStatus::create([
            'name' => 'Resolved',
            'description' => 'Complaint has been resolved',
            'color' => '#2ecc71',
            'is_active' => true,
        ]);

        // Admin updates the complaint status
        $response = $this->actingAs($this->admin)
            ->putJson('/api/complaints/' . $complaint->id, [
                'status_id' => $resolvedStatus->id,
                'is_resolved' => true,
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data',
            ]);

        // Check that the complaint status has been updated
        $this->assertDatabaseHas('complaints', [
            'id' => $complaint->id,
            'status_id' => $resolvedStatus->id,
            'is_resolved' => true,
        ]);
    }
}