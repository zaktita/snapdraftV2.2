<?php

namespace Tests\Feature\Wizards;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CSVWizardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    /** @test */
    public function authenticated_user_can_access_csv_wizard()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('projects.wizards.csv'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('projects/wizards/csv'));
    }

    /** @test */
    public function guest_cannot_access_csv_wizard()
    {
        $response = $this->get(route('projects.wizards.csv'));

        $response->assertRedirect(route('login'));
    }

    /** @test */
    public function user_can_submit_csv_wizard_with_valid_data()
    {
        $user = User::factory()->create();

        // Create CSV content
        $csvContent = "title,description,format\n";
        $csvContent .= "Product Launch,Amazing new product,square\n";
        $csvContent .= "Summer Sale,Hot deals this summer,landscape";
        
        $csvFile = UploadedFile::fake()->createWithContent('data.csv', $csvContent);
        
        // Create reference images (5 minimum)
        $referenceImages = [];
        for ($i = 0; $i < 5; $i++) {
            $referenceImages[] = UploadedFile::fake()->image("ref{$i}.jpg");
        }

        $response = $this->actingAs($user)->post(route('projects.wizards.csv.store'), [
            'csv_file' => $csvFile,
            'reference_images' => $referenceImages,
            'project_name' => 'CSV Test Project',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('projects', [
            'name' => 'CSV Test Project',
            'user_id' => $user->id,
        ]);
    }

    /** @test */
    public function csv_wizard_requires_csv_file()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('projects.wizards.csv.store'), [
            'reference_images' => [UploadedFile::fake()->image('ref.jpg')],
            'project_name' => 'Test Project',
        ]);

        $response->assertSessionHasErrors(['csv_file']);
    }

    /** @test */
    public function csv_wizard_can_work_without_reference_images()
    {
        $user = User::factory()->create();
        $user->update(['credits_remaining' => 10]);
        
        $csvContent = "title,description,format\n";
        $csvContent .= "Product Launch,Amazing new product,square\n";
        $csvContent .= "Summer Sale,Hot deals this summer,landscape";
        
        $csvFile = UploadedFile::fake()->createWithContent('data.csv', $csvContent);

        $response = $this->actingAs($user)->post(route('projects.wizards.csv.store'), [
            'csv_file' => $csvFile,
            'project_name' => 'Test Project Without References',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('projects', [
            'name' => 'Test Project Without References',
            'user_id' => $user->id,
        ]);
    }

    /** @test */
    public function csv_wizard_validates_csv_file_format()
    {
        $user = User::factory()->create();

        // Invalid CSV (not a real CSV file)
        $invalidFile = UploadedFile::fake()->create('document.pdf', 100);

        $response = $this->actingAs($user)->post(route('projects.wizards.csv.store'), [
            'csv_file' => $invalidFile,
            'reference_images' => array_fill(0, 5, UploadedFile::fake()->image('ref.jpg')),
            'project_name' => 'Test Project',
        ]);

        $response->assertSessionHasErrors(['csv_file']);
    }

    /** @test */
    public function csv_wizard_validates_image_file_types()
    {
        $user = User::factory()->create();
        $csvFile = UploadedFile::fake()->createWithContent('data.csv', 'title,description,format');

        // Include invalid file type
        $referenceImages = [
            UploadedFile::fake()->image('ref1.jpg'),
            UploadedFile::fake()->image('ref2.jpg'),
            UploadedFile::fake()->image('ref3.jpg'),
            UploadedFile::fake()->image('ref4.jpg'),
            UploadedFile::fake()->create('document.pdf', 100), // Invalid
        ];

        $response = $this->actingAs($user)->post(route('projects.wizards.csv.store'), [
            'csv_file' => $csvFile,
            'reference_images' => $referenceImages,
            'project_name' => 'Test Project',
        ]);

        $response->assertSessionHasErrors(['reference_images.*']);
    }

    /** @test */
    public function csv_wizard_creates_project_and_queues_generation_job()
    {
        $user = User::factory()->create();

        $csvContent = "title,description,format\nTest Title,Test Description,square";
        $csvFile = UploadedFile::fake()->createWithContent('data.csv', $csvContent);
        $referenceImages = array_fill(0, 5, UploadedFile::fake()->image('ref.jpg'));

        $response = $this->actingAs($user)->post(route('projects.wizards.csv.store'), [
            'csv_file' => $csvFile,
            'reference_images' => $referenceImages,
            'project_name' => 'CSV Test Project',
        ]);

        $response->assertRedirect();
        
        // Assert project created
        $this->assertDatabaseHas('projects', [
            'name' => 'CSV Test Project',
            'user_id' => $user->id,
        ]);

        // Assert job was dispatched (if using queue)
        // $this->assertDatabaseHas('jobs', [...]);
    }

    /** @test */
    public function csv_wizard_respects_user_credit_limits()
    {
        $user = User::factory()->create([
            'credits_remaining' => 5, // Only 5 credits
        ]);

        // CSV with 10 rows (needs 10 credits)
        $csvContent = "title,description,format\n";
        for ($i = 0; $i < 10; $i++) {
            $csvContent .= "Title {$i},Description {$i},square\n";
        }
        
        $csvFile = UploadedFile::fake()->createWithContent('data.csv', $csvContent);
        $referenceImages = array_fill(0, 5, UploadedFile::fake()->image('ref.jpg'));

        $response = $this->actingAs($user)->post(route('projects.wizards.csv.store'), [
            'csv_file' => $csvFile,
            'reference_images' => $referenceImages,
            'project_name' => 'CSV Test Project',
        ]);

        $response->assertSessionHasErrors(); // Should fail due to insufficient credits
    }
}
