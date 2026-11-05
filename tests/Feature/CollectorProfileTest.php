<?php

namespace Tests\Feature;

use App\Models\CollectorProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CollectorProfileTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['name' => 'Karim Olimov', 'email' => 'karim@example.com']);
    }

    public function test_a_new_user_has_no_profile_and_saving_one_keeps_it_off(): void
    {
        $this->assertNull($this->user->collectorProfile);

        Livewire::actingAs($this->user)
            ->test('pages::profile.edit')
            ->assertSet('displayName', '')
            ->set('displayName', 'Kitobxon')
            ->set('bio', 'I collect poetry')
            ->call('save')
            ->assertHasNoErrors();

        $profile = $this->user->collectorProfile()->sole();
        $this->assertSame('Kitobxon', $profile->display_name);
        $this->assertFalse($profile->isShared());
        $this->assertNull($profile->shareUrl());
    }

    public function test_profile_cannot_be_turned_on_before_it_is_saved(): void
    {
        Livewire::actingAs($this->user)
            ->test('pages::profile.edit')
            ->call('turnOn')
            ->assertHasErrors('displayName');

        $this->assertNull($this->user->collectorProfile()->first());
    }

    public function test_turned_on_page_shows_only_what_was_typed(): void
    {
        CollectorProfile::factory()->for($this->user)->create(['display_name' => 'Kitobxon', 'bio' => 'I collect poetry']);

        Livewire::actingAs($this->user)->test('pages::profile.edit')->call('turnOn');

        $url = $this->user->collectorProfile()->sole()->shareUrl();

        auth()->logout();

        $this->get($url)
            ->assertOk()
            ->assertSee('Kitobxon')
            ->assertSee('I collect poetry')
            ->assertSee('noindex, nofollow', false)
            ->assertDontSee('Karim Olimov')
            ->assertDontSee('karim@example.com');
    }

    public function test_turning_off_stops_the_link_and_turning_on_again_makes_a_new_one(): void
    {
        $profile = CollectorProfile::factory()->for($this->user)->shared()->create();
        $oldUrl = $profile->shareUrl();

        Livewire::actingAs($this->user)->test('pages::profile.edit')->call('turnOff');
        $this->get($oldUrl)->assertNotFound();

        Livewire::actingAs($this->user)->test('pages::profile.edit')->call('turnOn');
        $this->get($oldUrl)->assertNotFound();
        $this->get($profile->refresh()->shareUrl())->assertOk();
    }

    public function test_an_open_page_stops_working_when_the_profile_is_turned_off(): void
    {
        $profile = CollectorProfile::factory()->for($this->user)->shared()->create();

        $page = Livewire::test('pages::collectors.show', ['token' => $profile->share_token]);

        $profile->stopSharing();

        $page->call('$refresh')->assertNotFound();
    }

    public function test_unknown_link_is_not_found(): void
    {
        $this->get(route('collectors.show', 'nope'))->assertNotFound();
    }

    public function test_each_user_edits_only_their_own_profile(): void
    {
        $mine = CollectorProfile::factory()->for($this->user)->create(['display_name' => 'Mine']);
        $other = User::factory()->create();

        Livewire::actingAs($other)
            ->test('pages::profile.edit')
            ->assertSet('displayName', '')
            ->set('displayName', 'Theirs')
            ->call('save')
            ->call('turnOn');

        $this->assertSame('Mine', $mine->refresh()->display_name);
        $this->assertFalse($mine->isShared());
        $this->assertTrue($other->collectorProfile()->sole()->isShared());
    }

    public function test_editing_page_needs_a_verified_account(): void
    {
        $this->get(route('profile.edit'))->assertRedirect(route('login'));

        $this->actingAs(User::factory()->unverified()->create())
            ->get(route('profile.edit'))
            ->assertRedirect(route('verification.notice'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function contactDetails(): array
    {
        return [
            'phone' => ['Call +998 90 123-45-67'],
            'email' => ['write to karim@example.com'],
            'telegram username' => ['find me @kitobxon'],
            'telegram link' => ['t.me/kitobxon'],
            'web link' => ['https://example.com/me'],
        ];
    }

    #[DataProvider('contactDetails')]
    public function test_contact_details_are_refused_in_the_name_and_the_text(string $text): void
    {
        Livewire::actingAs($this->user)
            ->test('pages::profile.edit')
            ->set('displayName', 'Kitobxon')
            ->set('bio', $text)
            ->call('save')
            ->assertHasErrors('bio')
            ->set('bio', '')
            ->set('displayName', mb_substr($text, 0, 60))
            ->call('save')
            ->assertHasErrors('displayName');

        $this->assertNull($this->user->collectorProfile()->first());
    }

    public function test_ordinary_text_with_years_is_allowed(): void
    {
        Livewire::actingAs($this->user)
            ->test('pages::profile.edit')
            ->set('displayName', 'Kitobxon')
            ->set('bio', 'Collecting since 1998, about 300 books.')
            ->call('save')
            ->assertHasNoErrors();
    }
}
