<?php

declare(strict_types=1);

namespace Tests\Feature\Shared;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Src\Identity\Users\Domain\ValueObjects\Role;
use Src\Identity\Users\Infrastructure\Persistence\Models\UserModel;
use Src\Inventory\Products\Infrastructure\Persistence\Models\ProductModel;
use Src\Negocios\Profile\Infrastructure\Persistence\Models\NegocioModel;
use Src\Shared\Infrastructure\Storage\DiskImageStorage;
use Tests\Support\RefreshDatabaseAsOwner;
use Tests\Support\Shared\ScopesQueriesToNegocio;
use Tests\TestCase;

final class ImageUploadsTest extends TestCase
{
    use RefreshDatabaseAsOwner, ScopesQueriesToNegocio;

    private NegocioModel $negocio;

    private NegocioModel $otherNegocio;

    private ProductModel $product;

    /** Fake the private disk, create two negocios with a product each and act as the administrator of the first. */
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(DiskImageStorage::DISK);
        $this->negocio = NegocioModel::factory()->create();
        $this->otherNegocio = NegocioModel::factory()->create();
        $this->product = ProductModel::factory()->create(['negocio_id' => $this->negocio->id]);
        $this->actAs(Role::Administrador);
    }

    /** JPG, PNG and WebP photos are stored in the folder of the negocio under the hash of their content. */
    #[DataProvider('acceptedImages')]
    public function test_photo_is_stored_in_the_folder_of_the_negocio(string $name, string $extension): void
    {
        $image = UploadedFile::fake()->image($name, 300, 300);
        $expected = 'negocios/'.$this->negocio->id.'/productos/'.$this->product->id.'/'.hash_file('sha256', $image->getRealPath()).'.'.$extension;

        $this->post($this->photoUrl($this->product->id), ['imagen' => $image], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.foto', 'http://localhost'.$this->photoUrl($this->product->id));

        Storage::disk(DiskImageStorage::DISK)->assertExists($expected);
        $this->scopeQueriesTo($this->negocio->id);
        $this->assertDatabaseHas('productos', ['id' => $this->product->id, 'foto_path' => $expected]);
    }

    /**
     * Accepted images and the extension they are stored with.
     *
     * @return array<string, array{string, string}>
     */
    public static function acceptedImages(): array
    {
        return [
            'jpg' => ['arroz.jpg', 'jpg'],
            'jpeg' => ['arroz.jpeg', 'jpg'],
            'png' => ['arroz.png', 'png'],
            'webp' => ['arroz.webp', 'webp'],
        ];
    }

    /** Other types, oversized images and a missing file are rejected with a message that explains the rule. */
    #[DataProvider('rejectedFiles')]
    public function test_invalid_file_is_rejected(callable $file, string $message): void
    {
        $this->post($this->photoUrl($this->product->id), ['imagen' => $file()], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('errores.imagen.0', $message);

        $this->assertSame([], Storage::disk(DiskImageStorage::DISK)->allFiles());
    }

    /**
     * Files that are not accepted and the message each one produces.
     *
     * @return array<string, array{callable(): mixed, string}>
     */
    public static function rejectedFiles(): array
    {
        return [
            'pdf' => [fn () => UploadedFile::fake()->create('factura.pdf', 100, 'application/pdf'), 'La imagen debe ser JPG, PNG o WebP.'],
            'gif' => [fn () => UploadedFile::fake()->image('animado.gif'), 'La imagen debe ser JPG, PNG o WebP.'],
            'más de 2 MB' => [fn () => UploadedFile::fake()->image('enorme.jpg')->size(2049), 'La imagen no puede pesar más de 2 MB.'],
            'sin archivo' => [fn () => null, 'El campo imagen es obligatorio.'],
        ];
    }

    /** Replacing the photo removes the previous file. */
    public function test_replacing_the_photo_removes_the_previous_file(): void
    {
        $this->post($this->photoUrl($this->product->id), ['imagen' => UploadedFile::fake()->image('primera.png', 10, 10)], ['Accept' => 'application/json'])->assertOk();
        $this->post($this->photoUrl($this->product->id), ['imagen' => UploadedFile::fake()->image('segunda.png', 20, 20)], ['Accept' => 'application/json'])->assertOk();

        $this->assertCount(1, Storage::disk(DiskImageStorage::DISK)->allFiles());
    }

    /** Two products with the same image keep their own files: replacing the photo of one leaves the other intact. */
    public function test_products_with_the_same_image_do_not_share_the_file(): void
    {
        $other = ProductModel::factory()->create(['negocio_id' => $this->negocio->id]);
        $same = UploadedFile::fake()->image('igual.png', 50, 50);
        $contents = (string) file_get_contents($same->getRealPath());

        foreach ([$this->product->id, $other->id] as $productId) {
            $copy = UploadedFile::fake()->createWithContent('igual.png', $contents);
            $this->post($this->photoUrl($productId), ['imagen' => $copy], ['Accept' => 'application/json'])->assertOk();
        }

        $this->post($this->photoUrl($this->product->id), ['imagen' => UploadedFile::fake()->image('nueva.png', 60, 60)], ['Accept' => 'application/json'])->assertOk();

        $this->get($this->photoUrl($other->id))->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->assertCount(2, Storage::disk(DiskImageStorage::DISK)->allFiles());
    }

    /** The photo is sent back only through the authenticated route, with its image type. */
    public function test_photo_is_downloaded_through_the_api(): void
    {
        $this->post($this->photoUrl($this->product->id), ['imagen' => UploadedFile::fake()->image('arroz.png')], ['Accept' => 'application/json'])->assertOk();

        $this->get($this->photoUrl($this->product->id))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');
    }

    /** A product without photo answers 404. */
    public function test_product_without_photo_answers_not_found(): void
    {
        $this->getJson($this->photoUrl($this->product->id))
            ->assertNotFound()
            ->assertJsonPath('detail', 'El producto todavía no tiene foto.');
    }

    /** The photo of a product of another negocio can be neither replaced nor downloaded, even though the file exists. */
    public function test_photo_of_another_negocio_is_unreachable(): void
    {
        $foreign = ProductModel::factory()->create(['negocio_id' => $this->otherNegocio->id, 'foto_path' => 'negocios/'.$this->otherNegocio->id.'/productos/b.png']);
        Storage::disk(DiskImageStorage::DISK)->put('negocios/'.$this->otherNegocio->id.'/productos/b.png', 'imagen de B');

        $this->getJson($this->photoUrl($foreign->id))->assertNotFound()->assertJsonPath('detail', 'El producto no existe.');
        $this->post($this->photoUrl($foreign->id), ['imagen' => UploadedFile::fake()->image('robo.png')], ['Accept' => 'application/json'])->assertNotFound();

        $this->assertSame(['negocios/'.$this->otherNegocio->id.'/productos/b.png'], Storage::disk(DiskImageStorage::DISK)->allFiles());
    }

    /** The cashier sees the photo but cannot change it. */
    public function test_cashier_cannot_upload_a_photo(): void
    {
        $this->actAs(Role::Cajero);

        $this->post($this->photoUrl($this->product->id), ['imagen' => UploadedFile::fake()->image('arroz.png')], ['Accept' => 'application/json'])
            ->assertForbidden();
    }

    /** The administrator uploads the logo; the profile points to it and only its own negocio is reachable. */
    public function test_logo_is_stored_and_served_for_the_negocio_of_the_account(): void
    {
        $image = UploadedFile::fake()->image('logo.png', 200, 200);
        $expected = 'negocios/'.$this->negocio->id.'/logo/'.hash_file('sha256', $image->getRealPath()).'.png';

        $this->post('/api/v1/negocio/logo', ['imagen' => $image], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.logo', 'http://localhost/api/v1/negocio/logo');

        Storage::disk(DiskImageStorage::DISK)->assertExists($expected);
        $this->assertDatabaseHas('negocios', ['id' => $this->negocio->id, 'logo_path' => $expected]);
        $this->get('/api/v1/negocio/logo')->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->getJson('/api/v1/negocio')->assertJsonPath('data.logo', 'http://localhost/api/v1/negocio/logo');
    }

    /** The logo route never serves the logo of another negocio. */
    public function test_logo_route_ignores_the_logo_of_another_negocio(): void
    {
        $this->otherNegocio->update(['logo_path' => 'negocios/'.$this->otherNegocio->id.'/logo/b.png']);
        Storage::disk(DiskImageStorage::DISK)->put('negocios/'.$this->otherNegocio->id.'/logo/b.png', 'logo de B');

        $this->getJson('/api/v1/negocio/logo?negocio_id='.$this->otherNegocio->id)
            ->assertNotFound()
            ->assertJsonPath('detail', 'El negocio todavía no tiene logo.');
    }

    /** Only the administration changes the logo. */
    public function test_cashier_cannot_upload_the_logo(): void
    {
        $this->actAs(Role::Cajero);

        $this->post('/api/v1/negocio/logo', ['imagen' => UploadedFile::fake()->image('logo.png')], ['Accept' => 'application/json'])
            ->assertForbidden();
    }

    /** Route of the photo of a product. */
    private function photoUrl(string $productId): string
    {
        return "/api/v1/productos/{$productId}/foto";
    }

    /** Authenticate an account of the first negocio with the abilities of a role. */
    private function actAs(Role $role): void
    {
        Sanctum::actingAs(
            UserModel::factory()->role($role)->create(['negocio_id' => $this->negocio->id]),
            $role->abilities(),
        );
    }
}
