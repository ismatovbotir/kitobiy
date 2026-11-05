<?php

namespace Tests\Feature;

use App\Enums\LibraryRole;
use App\Models\Author;
use App\Models\Copy;
use App\Models\Edition;
use App\Models\Library;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GiftPageTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Library $library;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->library = Library::factory()->ownedBy($this->owner)->create(['name' => 'Karimovs']);
    }

    public function test_a_library_is_not_shared_by_default_and_has_no_link(): void
    {
        $this->assertFalse($this->library->isShared());
        $this->assertNull($this->library->shareUrl());
    }

    public function test_the_owner_turns_the_gift_page_on_and_off(): void
    {
        $page = Livewire::actingAs($this->owner)->test('pages::library.members');

        $page->call('shareWithNewLink')->assertHasNoErrors();
        $shareUrl = $this->library->fresh()->shareUrl();
        $this->assertNotNull($shareUrl);
        $page->assertSee($shareUrl);

        $this->get($shareUrl)->assertOk()->assertSee('Karimovs');

        $page->call('stopSharing');
        $this->assertFalse($this->library->fresh()->isShared());
        $this->get($shareUrl)->assertNotFound();
    }

    public function test_the_switch_explains_the_goals_until_it_is_turned_on(): void
    {
        $page = Livewire::actingAs($this->owner)->test('pages::library.members')
            ->assertSeeHtml('role="switch" aria-checked="false"')
            ->assertSee(__('No repeat gifts'))
            ->assertSee(__('Friends will never see'));

        $page->call('shareWithNewLink')
            ->assertSeeHtml('role="switch" aria-checked="true"')
            ->assertDontSee(__('No repeat gifts'))
            ->assertSee(__('Friends will never see'));
    }

    public function test_a_member_who_cannot_share_sees_no_switch(): void
    {
        $member = User::factory()->create();
        $this->library->members()->attach($member, ['role' => LibraryRole::Member->value]);

        Livewire::actingAs($member)->test('pages::library.members')
            ->assertDontSeeHtml('role="switch"')
            ->assertSee(__('The gift page is off. The owner or an administrator can turn it on.'));
    }

    public function test_the_home_page_suggests_the_gift_page_once_there_are_enough_books(): void
    {
        $prompt = __('Let friends see your books so they can choose the right gift.');
        Copy::factory()->for($this->library)->count(4)->create();

        Livewire::actingAs($this->owner)->test('pages::dashboard')->assertDontSee($prompt);

        Copy::factory()->for($this->library)->create();

        Livewire::actingAs($this->owner)->test('pages::dashboard')
            ->assertSee($prompt)
            ->assertSeeHtml(route('library.members').'#gift-page');
    }

    public function test_the_home_page_prompt_is_hidden_when_shared_or_when_the_user_cannot_share(): void
    {
        $prompt = __('Let friends see your books so they can choose the right gift.');
        Copy::factory()->for($this->library)->count(5)->create();
        $member = User::factory()->create();
        $this->library->members()->attach($member, ['role' => LibraryRole::Member->value]);

        Livewire::actingAs($member)->test('pages::dashboard')->assertDontSee($prompt);

        $this->library->shareWithNewLink();

        Livewire::actingAs($this->owner)->test('pages::dashboard')->assertDontSee($prompt);
    }

    public function test_a_new_link_replaces_the_old_one(): void
    {
        $this->library->shareWithNewLink();
        $oldUrl = $this->library->shareUrl();

        Livewire::actingAs($this->owner)->test('pages::library.members')->call('shareWithNewLink');

        $newUrl = $this->library->fresh()->shareUrl();
        $this->assertNotSame($oldUrl, $newUrl);
        $this->get($oldUrl)->assertNotFound();
        $this->get($newUrl)->assertOk();
    }

    public function test_only_owners_and_admins_can_change_sharing(): void
    {
        $admin = User::factory()->create();
        $member = User::factory()->create();
        $this->library->members()->attach($admin, ['role' => LibraryRole::Admin->value]);
        $this->library->members()->attach($member, ['role' => LibraryRole::Member->value]);

        Livewire::actingAs($admin)->test('pages::library.members')->call('shareWithNewLink')->assertOk();
        $this->assertTrue($this->library->fresh()->isShared());

        Livewire::actingAs($member)->test('pages::library.members')
            ->assertSee($this->library->fresh()->shareUrl())
            ->assertDontSee(__('New link'))
            ->call('stopSharing')
            ->assertForbidden();

        $this->assertTrue($this->library->fresh()->isShared());
    }

    public function test_guests_see_each_edition_once_without_private_details(): void
    {
        $this->library->shareWithNewLink();
        $author = Author::factory()->create(['name' => 'Abdulla Qodiriy']);
        $edition = Edition::factory()->create(['title' => 'O‘tkan kunlar']);
        $edition->authors()->attach($author, ['position' => 0]);
        $copy = Copy::factory()->for($this->library)->for($edition)->create(['notes' => 'Secret family note']);
        Copy::factory()->for($this->library)->for($edition)->create();
        $copy->tags()->attach(Tag::factory()->for($this->library)->create(['name' => 'Private tag']));
        Copy::factory()->for(Library::factory())->for(Edition::factory()->state(['title' => 'Other library book']))->create();

        $this->assertGuest();
        $response = $this->get($this->library->shareUrl())
            ->assertOk()
            ->assertSee('O‘tkan kunlar')
            ->assertSee('Abdulla Qodiriy')
            ->assertSee(trans_choice(':count book|:count books', 1))
            ->assertSee('noindex', false)
            ->assertDontSee('Secret family note')
            ->assertDontSee('Private tag')
            ->assertDontSee($this->owner->email)
            ->assertDontSee('Other library book');

        $this->assertSame(1, substr_count($response->getContent(), 'wire:key="edition-'.$edition->id.'"'));
    }

    public function test_friends_check_whether_a_book_is_already_there(): void
    {
        $this->library->shareWithNewLink();
        Copy::factory()->for($this->library)->for(Edition::factory()->state(['title' => 'Kecha va kunduz']))->create();

        Livewire::test('pages::gift.show', ['token' => $this->library->share_token])
            ->set('search', 'Кеча')
            ->assertSee(__('They already have this. Better pick another book.'))
            ->set('search', 'Sariq devni minib')
            ->assertSee(__('Not on their shelves. It could make a good gift!'))
            ->assertDontSee('Kecha va kunduz');
    }

    public function test_a_hidden_book_stays_off_the_gift_page_and_its_count_and_check(): void
    {
        $this->library->shareWithNewLink();
        Copy::factory()->for($this->library)->for(Edition::factory()->state(['title' => 'Visible book']))->create();
        $edition = Edition::factory()->create(['title' => 'Private diary book']);
        Copy::factory()->for($this->library)->for($edition)->hiddenFromGiftPage()->create();
        Copy::factory()->for($this->library)->for($edition)->create();

        Livewire::test('pages::gift.show', ['token' => $this->library->share_token])
            ->assertSee('Visible book')
            ->assertDontSee('Private diary book')
            ->assertSee(trans_choice(':count book|:count books', 1))
            ->set('search', 'Private diary')
            ->assertSee(__('Not on their shelves. It could make a good gift!'));
    }

    public function test_hiding_on_the_book_page_covers_every_copy_of_the_edition_in_this_library_only(): void
    {
        $edition = Edition::factory()->create();
        $copy = Copy::factory()->for($this->library)->for($edition)->create();
        $secondCopy = Copy::factory()->for($this->library)->for($edition)->create();
        $otherLibraryCopy = Copy::factory()->for(Library::factory())->for($edition)->create();

        $page = Livewire::actingAs($this->owner)->test('pages::books.show', ['copy' => $copy])
            ->assertSee(__('Shown on your gift page.'))
            ->call('toggleGiftPageVisibility')
            ->assertSee(__('Hidden from your gift page.'));

        $this->assertTrue($secondCopy->fresh()->hidden_from_gift_page);
        $this->assertFalse($otherLibraryCopy->fresh()->hidden_from_gift_page);

        $page->call('toggleGiftPageVisibility')->assertSee(__('Shown on your gift page.'));
        $this->assertFalse($copy->fresh()->isHiddenFromGiftPage());
    }

    public function test_a_viewer_cannot_hide_a_book(): void
    {
        $viewer = User::factory()->create();
        $this->library->members()->attach($viewer, ['role' => LibraryRole::Viewer->value]);
        $copy = Copy::factory()->for($this->library)->create();

        Livewire::actingAs($viewer)->test('pages::books.show', ['copy' => $copy])
            ->assertDontSee(__('Hide this book'))
            ->call('toggleGiftPageVisibility')
            ->assertForbidden();

        $this->assertFalse($copy->fresh()->isHiddenFromGiftPage());
    }

    public function test_the_family_page_links_to_the_list_of_hidden_books(): void
    {
        $edition = Edition::factory()->create(['title' => 'Private diary book']);
        Copy::factory()->for($this->library)->for($edition)->hiddenFromGiftPage()->count(2)->create();
        Copy::factory()->for($this->library)->for(Edition::factory()->state(['title' => 'Visible book']))->create();

        Livewire::actingAs($this->owner)->test('pages::library.members')
            ->assertSee(trans_choice(':count book hidden|:count books hidden', 1))
            ->assertSeeHtml(e(route('books.index', ['hidden' => 1])));

        Livewire::actingAs($this->owner)->withQueryParams(['hidden' => 1])->test('pages::books.index')
            ->assertSee('Private diary book')
            ->assertDontSee('Visible book');
    }

    public function test_an_unknown_or_turned_off_link_is_not_found(): void
    {
        $this->get(route('gift.show', 'not-a-real-token'))->assertNotFound();

        $this->library->shareWithNewLink();
        $page = Livewire::test('pages::gift.show', ['token' => $this->library->share_token]);
        $this->library->stopSharing();

        $page->set('search', 'anything')->assertNotFound();
    }
}
