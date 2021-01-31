<?php

namespace Morrislaptop\LaravelPopoCaster\Tests;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Morrislaptop\LaravelPopoCaster\Caster;
use Symfony\Component\Serializer\Exception\MissingConstructorArgumentsException;

class CasterTest extends TestCase
{
    /** @test */
    public function it_casts_arrays_to_json()
    {
        User::factory()->create([
            'address' => [
                'street' => '1640 Riverside Drive',
                'suburb' => 'Hill Valley',
                'state' => 'California',
                'moved' => '2010-01-12T11:00:00+09:00',
            ],
            'addresses' => [
                [
                    'street' => '1641 Riverside Drive',
                    'suburb' => 'Hill Valley',
                    'state' => 'California',
                    'moved' => '2010-01-12T11:00:00+09:00',
                ],
            ],
        ]);

        $this->assertDatabaseHas('users', [
            'address->street' => '1640 Riverside Drive',
            'address->suburb' => 'Hill Valley',
            'address->state' => 'California',
            'address->moved' => '2010-01-12T11:00:00+09:00',
        ]);
    }

    /** @test */
    public function it_casts_data_transfer_objects_to_json()
    {
        User::factory()->create([
            'address' => new Address(
                street: '1640 Riverside Drive',
                suburb: 'Hill Valley',
                state: 'California',
                moved: Carbon::parse('2010-01-12T11:00:00+09:00'),
            ),
        ]);

        $this->assertDatabaseHas('users', [
            'address->street' => '1640 Riverside Drive',
            'address->suburb' => 'Hill Valley',
            'address->state' => 'California',
            'address->moved' => '2010-01-12T11:00:00+09:00',
        ]);
    }

    /** @test */
    public function it_json_to_a_data_transfer_object()
    {
        $user = User::factory()->create([
            'address' => [
                'street' => '1640 Riverside Drive',
                'suburb' => 'Hill Valley',
                'state' => 'California',
                'moved' => '2010-01-12T11:00:00+09:00',
            ],
            'addresses' => [
                [
                    'street' => '1641 Riverside Drive',
                    'suburb' => 'Hill Valley',
                    'state' => 'California',
                    'moved' => '2010-01-12T11:00:00+09:00',
                ],
            ],
        ]);

        $user = $user->fresh();

        $this->assertInstanceOf(Address::class, $user->address);
        $this->assertEquals('1640 Riverside Drive', $user->address->street);
        $this->assertEquals('Hill Valley', $user->address->suburb);
        $this->assertEquals('California', $user->address->state);
        $this->assertEquals('2010-01-12T11:00:00+09:00', $user->address->moved->toIso8601String());
        $this->assertEquals('1641 Riverside Drive', $user->addresses[0]->street);
    }

    /** @test */
    public function it_throws_exceptions_for_incorrect_data_structures()
    {
        $this->expectException(MissingConstructorArgumentsException::class);

        User::factory()->create([
            'address' => [
                'bad' => 'thing',
            ],
        ]);
    }

    /** @test */
    public function it_rejects_invalid_types()
    {
        $this->expectException(InvalidArgumentException::class);

        User::factory()->create([
            'address' => 'string',
        ]);
    }

    /** @test */
    public function it_handles_nullable_columns()
    {
        $user = User::factory()->create(['address' => null]);

        $this->assertDatabaseHas('users', ['address' => null]);

        $this->assertNull($user->refresh()->address);
    }
}

class Address
{
    public function __construct(
        public string $street,
        public string $suburb,
        public string $state,
        public Carbon $moved,
    ) {
    }
}

/**
 * @var Address $address
 */
class User extends Model
{
    use HasFactory;

    protected $casts = [
        'address' => Caster::class . ':' . Address::class,
        'addresses' => Caster::class . ':' . Address::class . '[]',
    ];

    protected static function newFactory()
    {
        return UserFactory::new();
    }
}

class UserFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = User::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'name' => $this->faker->name,
            'email' => $this->faker->unique()->safeEmail,
            'email_verified_at' => now(),
            'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', // password
            'remember_token' => Str::random(10),
            'address' => [
                'street' => $this->faker->streetAddress,
                'suburb' => $this->faker->city,
                'state' => $this->faker->state,
                'moved' => now(),
            ],
        ];
    }
}
