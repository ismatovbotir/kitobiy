<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_sees_the_landing_page_in_uzbek_by_default(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('lang="uz"', false)
            ->assertSee('Uyingizdagi kutubxona endi tartibda')
            ->assertSee(route('register'))
            ->assertSee(route('login'));
    }

    public function test_landing_page_follows_the_chosen_language(): void
    {
        $this->post(route('locale.update', 'ru'));

        $this->get(route('home'))
            ->assertSee('lang="ru"', false)
            ->assertSee('Домашняя библиотека в полном порядке');
    }

    public function test_signed_in_user_goes_to_the_dashboard(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('home'))
            ->assertRedirect(route('dashboard'));
    }

    public function test_search_engines_may_index_the_landing_page(): void
    {
        $this->get(route('home'))
            ->assertDontSee('noindex')
            ->assertSee('name="description"', false)
            ->assertSee('rel="canonical"', false);
    }

    public function test_every_saying_links_to_its_source(): void
    {
        $response = $this->get(route('home'));

        foreach (range(1, 5) as $note) {
            $response->assertSee('href="#note-'.$note.'"', false)
                ->assertSee('id="note-'.$note.'"', false);
        }
    }

    public function test_landing_page_links_to_the_about_page(): void
    {
        $this->get(route('home'))->assertSee(route('about'));
    }

    public function test_about_page_tells_the_story_in_the_chosen_language(): void
    {
        $this->get(route('about'))
            ->assertOk()
            ->assertSee('Kitobiy haqida')
            ->assertSee(route('register'));

        $this->post(route('locale.update', 'ru'));

        $this->get(route('about'))
            ->assertSee('lang="ru"', false)
            ->assertSee('Короткое предисловие для тех, кто любит книги');
    }

    public function test_search_engines_may_index_the_about_page(): void
    {
        $this->get(route('about'))
            ->assertDontSee('noindex')
            ->assertSee('name="description"', false)
            ->assertSee('rel="canonical"', false);
    }

    public function test_every_saying_on_the_about_page_links_to_its_source(): void
    {
        $response = $this->get(route('about'));

        foreach (range(1, 2) as $note) {
            $response->assertSee('href="#note-'.$note.'"', false)
                ->assertSee('id="note-'.$note.'"', false);
        }
    }

    public function test_signed_in_user_reads_the_about_page_without_a_sign_up_invitation(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('about'))
            ->assertOk()
            ->assertSee(route('dashboard'))
            ->assertDontSee(route('register'));
    }

    public function test_market_is_not_promised_even_while_it_is_on(): void
    {
        config(['market.enabled' => true]);

        $this->get(route('home'))->assertDontSee('/market');
    }
}
