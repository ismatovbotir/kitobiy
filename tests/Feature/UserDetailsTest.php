<?php

namespace Tests\Feature;

use App\Models\CollectorProfile;
use App\Models\User;
use App\Support\Country;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UserDetailsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    public function test_every_detail_is_optional(): void
    {
        Livewire::actingAs($this->user)
            ->test('pages::profile.edit')
            ->call('saveDetails')
            ->assertHasNoErrors();

        $this->user->refresh();

        $this->assertNull($this->user->country_code);
        $this->assertNull($this->user->region);
        $this->assertNull($this->user->city);
        $this->assertNull($this->user->birth_date);
    }

    public function test_user_in_uzbekistan_chooses_region_and_city(): void
    {
        Livewire::actingAs($this->user)
            ->test('pages::profile.edit')
            ->set('countryCode', 'UZ')
            ->set('region', 'UZ-SA')
            ->assertSee('Kattaqo‘rg‘on')
            ->set('city', 'Samarqand')
            ->set('birthDate', '2001-05-20')
            ->call('saveDetails')
            ->assertHasNoErrors();

        $this->user->refresh();

        $this->assertSame('UZ', $this->user->country_code);
        $this->assertSame('UZ-SA', $this->user->region);
        $this->assertSame('Samarqand', $this->user->city);
        $this->assertSame('2001-05-20', $this->user->birth_date->format('Y-m-d'));
    }

    public function test_saved_details_fill_the_form_again(): void
    {
        $this->user->update(['country_code' => 'UZ', 'region' => 'UZ-XO', 'city' => 'Xiva', 'birth_date' => '1990-01-15']);

        Livewire::actingAs($this->user)
            ->test('pages::profile.edit')
            ->assertSet('countryCode', 'UZ')
            ->assertSet('region', 'UZ-XO')
            ->assertSet('city', 'Xiva')
            ->assertSet('birthDate', '1990-01-15');
    }

    public function test_city_outside_the_suggestions_is_accepted(): void
    {
        Livewire::actingAs($this->user)
            ->test('pages::profile.edit')
            ->set('countryCode', 'UZ')
            ->set('region', 'UZ-AN')
            ->set('city', 'Bo‘ston')
            ->call('saveDetails')
            ->assertHasNoErrors();

        $this->assertSame('Bo‘ston', $this->user->refresh()->city);
    }

    public function test_region_of_uzbekistan_must_come_from_the_list(): void
    {
        Livewire::actingAs($this->user)
            ->test('pages::profile.edit')
            ->set('countryCode', 'UZ')
            ->set('region', 'Atlantis')
            ->call('saveDetails')
            ->assertHasErrors(['region']);
    }

    public function test_other_country_takes_a_typed_region(): void
    {
        Livewire::actingAs($this->user)
            ->test('pages::profile.edit')
            ->set('countryCode', 'DE')
            ->set('region', 'Bayern')
            ->set('city', 'München')
            ->call('saveDetails')
            ->assertHasNoErrors();

        $this->user->refresh();

        $this->assertSame('DE', $this->user->country_code);
        $this->assertSame('Bayern', $this->user->region);
        $this->assertSame('München', $this->user->city);
    }

    public function test_unknown_country_is_refused(): void
    {
        Livewire::actingAs($this->user)
            ->test('pages::profile.edit')
            ->set('countryCode', 'XX')
            ->call('saveDetails')
            ->assertHasErrors(['countryCode']);
    }

    public function test_changing_country_clears_region_and_city(): void
    {
        Livewire::actingAs($this->user)
            ->test('pages::profile.edit')
            ->set('countryCode', 'UZ')
            ->set('region', 'UZ-SA')
            ->set('city', 'Urgut')
            ->set('countryCode', 'KZ')
            ->assertSet('region', '')
            ->assertSet('city', '');
    }

    public function test_clearing_the_country_removes_region_and_city(): void
    {
        $this->user->update(['country_code' => 'UZ', 'region' => 'UZ-XO', 'city' => 'Xiva']);

        Livewire::actingAs($this->user)
            ->test('pages::profile.edit')
            ->set('countryCode', '')
            ->call('saveDetails')
            ->assertHasNoErrors();

        $this->user->refresh();

        $this->assertNull($this->user->country_code);
        $this->assertNull($this->user->region);
        $this->assertNull($this->user->city);
    }

    public function test_date_of_birth_must_be_believable(): void
    {
        $page = Livewire::actingAs($this->user)->test('pages::profile.edit');

        $page->set('birthDate', now()->addDay()->format('Y-m-d'))->call('saveDetails')->assertHasErrors(['birthDate' => 'before']);
        $page->set('birthDate', '1850-01-01')->call('saveDetails')->assertHasErrors(['birthDate' => 'after']);
        $page->set('birthDate', 'not a date')->call('saveDetails')->assertHasErrors(['birthDate']);
    }

    public function test_details_stay_out_of_the_public_collector_page_and_serialized_user(): void
    {
        $this->user->update(['country_code' => 'UZ', 'region' => 'UZ-XO', 'city' => 'Xiva', 'birth_date' => '1990-01-15']);
        $profile = CollectorProfile::factory()->for($this->user)->create();
        $profile->shareWithNewLink();

        $this->get(route('collectors.show', $profile->share_token))
            ->assertOk()
            ->assertDontSee('Xiva')
            ->assertDontSee('1990');

        $this->assertArrayNotHasKey('birth_date', $this->user->toArray());
        $this->assertArrayNotHasKey('city', $this->user->toArray());
    }

    public function test_every_country_and_region_has_a_name_in_every_language(): void
    {
        foreach (config('app.supported_locales') as $locale) {
            foreach (Country::CODES as $code) {
                $this->assertNotSame("countries.{$code}", trans("countries.{$code}", [], $locale), "{$locale} {$code}");
            }

            foreach (array_keys(Country::UZBEK_REGIONS) as $code) {
                $this->assertNotSame("regions.{$code}", trans("regions.{$code}", [], $locale), "{$locale} {$code}");
            }
        }
    }
}
