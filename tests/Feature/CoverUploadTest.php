<?php

namespace Tests\Feature;

use App\Enums\LibraryRole;
use App\Models\Copy;
use App\Models\Edition;
use App\Models\Library;
use App\Models\User;
use App\Services\CoverImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class CoverUploadTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Library $library;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->user = User::factory()->create();
        $this->library = Library::factory()->withMember($this->user)->create();
    }

    /**
     * @param  array<string, mixed>  $editionAttributes
     */
    private function copyWithEdition(array $editionAttributes = []): Copy
    {
        return Copy::factory()->for($this->library)
            ->for(Edition::factory()->create(['cover_url' => null, ...$editionAttributes]))
            ->create();
    }

    private function alsoHeldByAnotherLibrary(Copy $copy): void
    {
        Copy::factory()->for(Library::factory()->create())->for($copy->edition)->create();
    }

    private function page(Copy $copy): Testable
    {
        return Livewire::actingAs($this->user)->test('pages::books.show', ['copy' => $copy]);
    }

    private function photo(int $width = 800, int $height = 1200): UploadedFile
    {
        return UploadedFile::fake()->image('cover.jpg', $width, $height);
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function storedSize(string $path): array
    {
        return array_slice(getimagesizefromstring(Storage::disk('public')->get($path)), 0, 2);
    }

    public function test_member_adds_a_cover_that_is_shrunk_to_webp(): void
    {
        $copy = $this->copyWithEdition();

        $this->page($copy)
            ->assertSee(__('Add cover photo'))
            ->set('coverPhoto', $this->photo(1200, 1800))
            ->assertHasNoErrors()
            ->assertSet('coverPhoto', null);

        $path = $copy->edition->fresh()->cover_path;

        Storage::disk('public')->assertExists($path);
        $this->assertStringEndsWith('.webp', $path);
        $this->assertSame([600, 900], $this->storedSize($path));
        $this->assertSame(Storage::disk('public')->url($path), $copy->edition->fresh()->cover_image_url);
    }

    public function test_a_small_photo_is_not_enlarged(): void
    {
        $path = app(CoverImage::class)->store($this->photo(300, 400));

        $this->assertSame([300, 400], $this->storedSize($path));
    }

    public function test_a_phone_photo_is_turned_upright_from_its_exif_rotation(): void
    {
        $tiff = "II*\0\x08\0\0\0\x01\0\x12\x01\x03\0\x01\0\0\0\x06\0\0\0\0\0\0\0";
        $exif = "Exif\0\0".$tiff;
        $jpeg = $this->photo(400, 200)->get();
        $withExif = substr($jpeg, 0, 2)."\xFF\xE1".pack('n', strlen($exif) + 2).$exif.substr($jpeg, 2);

        $path = app(CoverImage::class)->store(UploadedFile::fake()->createWithContent('photo.jpg', $withExif));

        $this->assertSame([200, 400], $this->storedSize($path));
    }

    public function test_an_unreadable_file_is_not_stored(): void
    {
        $this->assertNull(app(CoverImage::class)->store(UploadedFile::fake()->createWithContent('cover.jpg', 'not an image')));
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_non_images_and_oversized_photos_are_rejected(): void
    {
        $copy = $this->copyWithEdition();

        $this->page($copy)
            ->set('coverPhoto', UploadedFile::fake()->create('cover.pdf', 100, 'application/pdf'))
            ->assertHasErrors(['coverPhoto' => 'mimes'])
            ->set('coverPhoto', $this->photo(4201, 200))
            ->assertHasErrors(['coverPhoto' => 'dimensions'])
            ->set('coverPhoto', $this->photo()->size(10241))
            ->assertHasErrors(['coverPhoto' => 'max']);

        $this->assertNull($copy->edition->fresh()->cover_path);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_viewer_cannot_upload_or_remove_a_cover(): void
    {
        $copy = $this->copyWithEdition();
        $viewer = User::factory()->create();
        $this->library->members()->attach($viewer, ['role' => LibraryRole::Viewer->value]);

        Livewire::actingAs($viewer)->test('pages::books.show', ['copy' => $copy])
            ->assertDontSee(__('Add cover photo'))
            ->set('coverPhoto', $this->photo())
            ->assertForbidden();

        Livewire::actingAs($viewer)->test('pages::books.show', ['copy' => $copy])
            ->call('removeCover')
            ->assertForbidden();

        $this->assertNull($copy->edition->fresh()->cover_path);
    }

    public function test_shared_edition_without_a_cover_accepts_one_and_then_locks(): void
    {
        $copy = $this->copyWithEdition();
        $this->alsoHeldByAnotherLibrary($copy);

        $page = $this->page($copy)->set('coverPhoto', $this->photo())->assertHasNoErrors();
        $path = $copy->edition->fresh()->cover_path;

        $this->assertNotNull($path);
        $page->assertDontSee(__('Change cover'))->assertDontSee(__('Remove photo'));

        $this->page($copy->fresh())->set('coverPhoto', $this->photo())->assertForbidden();
        $this->page($copy->fresh())->call('removeCover')->assertForbidden();

        $this->assertSame($path, $copy->edition->fresh()->cover_path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_shared_edition_with_a_lookup_cover_cannot_be_overridden(): void
    {
        $copy = $this->copyWithEdition(['cover_url' => 'https://covers.example.test/1.jpg']);
        $this->alsoHeldByAnotherLibrary($copy);

        $this->page($copy)
            ->assertDontSee(__('Change cover'))
            ->set('coverPhoto', $this->photo())
            ->assertForbidden();
    }

    public function test_sole_holder_replaces_a_wrong_cover_and_the_old_file_is_deleted(): void
    {
        $copy = $this->copyWithEdition(['cover_url' => 'https://covers.example.test/1.jpg']);

        $this->page($copy)->assertSee(__('Change cover'))->set('coverPhoto', $this->photo())->assertHasNoErrors();
        $firstPath = $copy->edition->fresh()->cover_path;

        $this->page($copy->fresh())->set('coverPhoto', $this->photo())->assertHasNoErrors();
        $secondPath = $copy->edition->fresh()->cover_path;

        $this->assertNotSame($firstPath, $secondPath);
        Storage::disk('public')->assertMissing($firstPath);
        Storage::disk('public')->assertExists($secondPath);
    }

    public function test_removing_the_photo_returns_to_the_lookup_cover(): void
    {
        $copy = $this->copyWithEdition(['cover_url' => 'https://covers.example.test/1.jpg']);

        $this->page($copy)->set('coverPhoto', $this->photo());
        $path = $copy->edition->fresh()->cover_path;

        $this->page($copy->fresh())->assertSee(__('Remove photo'))->call('removeCover')->assertHasNoErrors();

        Storage::disk('public')->assertMissing($path);
        $this->assertSame('https://covers.example.test/1.jpg', $copy->edition->fresh()->cover_image_url);
    }

    public function test_the_book_list_shows_the_uploaded_cover(): void
    {
        $copy = $this->copyWithEdition(['cover_path' => null]);
        $copy->edition->setUploadedCover('covers/uploaded.webp');

        $this->actingAs($this->user)->get(route('books.index'))
            ->assertOk()
            ->assertSee(Storage::disk('public')->url('covers/uploaded.webp'));
    }
}
