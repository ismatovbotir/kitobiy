<?php

namespace Tests\Feature;

use App\Enums\LibraryRole;
use App\Models\Library;
use App\Models\User;
use App\Models\WishlistItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class WishlistTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Library $library;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->library = Library::factory()->ownedBy($this->owner)->create();
    }

    public function test_a_member_adds_and_removes_a_wish(): void
    {
        $page = Livewire::actingAs($this->owner)->test('pages::library.wishlist')
            ->set('title', 'Kecha va kunduz')
            ->set('author', 'Choʻlpon')
            ->call('add')
            ->assertHasNoErrors()
            ->assertSet('title', '')
            ->assertSee('Kecha va kunduz')
            ->assertSee('Choʻlpon');

        $wish = $this->library->wishlistItems()->sole();

        $page->call('remove', $wish->id)->assertDontSee('Kecha va kunduz');
        $this->assertDatabaseCount('wishlist_items', 0);
    }

    public function test_the_same_book_is_not_wished_twice_however_it_is_written(): void
    {
        WishlistItem::factory()->for($this->library)->create(['title' => 'Kecha va kunduz']);

        Livewire::actingAs($this->owner)->test('pages::library.wishlist')
            ->set('title', 'Кеча ва кундуз')
            ->call('add')
            ->assertHasErrors('title');

        $this->assertSame(1, $this->library->wishlistItems()->count());
    }

    public function test_a_viewer_can_look_but_not_change_the_wishlist(): void
    {
        $viewer = User::factory()->create();
        $this->library->members()->attach($viewer, ['role' => LibraryRole::Viewer->value]);
        $wish = WishlistItem::factory()->for($this->library)->create(['title' => 'Sariq devni minib']);

        Livewire::actingAs($viewer)->test('pages::library.wishlist')
            ->assertSee('Sariq devni minib')
            ->assertDontSee(__('Add to wishlist'))
            ->call('remove', $wish->id)
            ->assertForbidden();

        Livewire::actingAs($viewer)->test('pages::library.wishlist')
            ->set('title', 'Another book')
            ->call('add')
            ->assertForbidden();

        $this->assertSame(1, $this->library->wishlistItems()->count());
    }

    public function test_a_wish_of_another_library_cannot_be_removed(): void
    {
        $otherWish = WishlistItem::factory()->create();

        Livewire::actingAs($this->owner)->test('pages::library.wishlist')
            ->call('remove', $otherWish->id)
            ->assertNotFound();

        $this->assertModelExists($otherWish);
    }

    public function test_friends_see_wishes_on_the_gift_page_unless_they_are_searching(): void
    {
        $this->library->shareWithNewLink();
        WishlistItem::factory()->for($this->library)->create(['title' => 'Sariq devni minib']);
        WishlistItem::factory()->create(['title' => 'Wish of another library']);

        Livewire::test('pages::gift.show', ['token' => $this->library->share_token])
            ->assertSee(__('On their wishlist'))
            ->assertSee('Sariq devni minib')
            ->assertDontSee('Wish of another library')
            ->set('search', 'something')
            ->assertDontSee(__('On their wishlist'));
    }

    public function test_adding_the_wished_book_removes_only_the_exact_title_from_the_wishlist(): void
    {
        WishlistItem::factory()->for($this->library)->create(['title' => 'Кеча ва кундуз']);
        WishlistItem::factory()->for($this->library)->create(['title' => 'Kecha']);

        Livewire::actingAs($this->owner)->test('pages::books.create')
            ->call('addWithoutIsbn')
            ->set('title', 'Kecha va kunduz')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee(__('“:title” added to your library and removed from your wishlist.', ['title' => 'Kecha va kunduz']));

        $this->assertSame(['Kecha'], $this->library->wishlistItems()->pluck('title')->all());
    }
}
