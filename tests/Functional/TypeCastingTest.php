<?php

namespace Ufo\RestBundleTest\Functional;

/**
 * Path, query and form values are strings; they are cast to the declared scalar types of the method.
 */
class TypeCastingTest extends FunctionalTestCase
{
    public function testPathAndQueryValuesAreCast(): void
    {
        self::bootKernel();

        self::assertSame(
            [200, ['id' => 42, 'limit' => null, 'ratio' => 0.5, 'active' => true, 'ref' => '7', 'code' => '007']],
            $this->json('/typed-api/typed/items/42?limit=&ratio=0.5&active=true&ref=7&code=007'),
            'int|string accepts a string, so it is kept'
        );
        self::assertSame(
            [200, ['id' => -1, 'limit' => 5, 'ratio' => 2.5, 'active' => false, 'ref' => 'abc', 'code' => '']],
            $this->json('/typed-api/typed/items/-1?limit=5&ratio=2.5&active=0&ref=abc')
        );
    }

    public function testDefaultsStayWhenValuesAreAbsent(): void
    {
        self::bootKernel();

        self::assertSame(
            [200, ['id' => 1, 'limit' => 10, 'ratio' => 1.5, 'active' => false, 'ref' => 0, 'code' => '']],
            $this->json('/typed-api/typed/items/1')
        );
    }

    public function testValuesThatAreNotOfTheTypeAreReported(): void
    {
        self::bootKernel();

        [$status, $problem] = $this->json('/typed-api/typed/items/abc');
        self::assertSame(400, $status);
        self::assertSame(['id' => 'Parameter "id" must be of type int, string given'], $problem['detail-data']);

        foreach (['active=yes', 'ratio=1,5', 'limit=1.5'] as $query) {
            self::assertSame(400, $this->json('/typed-api/typed/items/1?'.$query)[0], $query);
        }
    }

    public function testJsonBodyIsNotCast(): void
    {
        self::bootKernel();

        self::assertSame([200, ['id' => 3, 'amount' => 5]], $this->json('/typed-api/typed/items/3', 'PATCH', '{"amount":5}'));
        [$status, $problem] = $this->json('/typed-api/typed/items/3', 'PATCH', '{"amount":"5"}');
        self::assertSame(400, $status, 'a JSON string stays a string');
        self::assertArrayHasKey('amount', $problem['detail-data']);
    }

    public function testFormValuesAreCast(): void
    {
        self::bootKernel();
        $request = \Symfony\Component\HttpFoundation\Request::create('http://localhost/typed-api/typed/items/3', 'PATCH', ['amount' => '9'],
            server: ['CONTENT_TYPE' => 'application/x-www-form-urlencoded'], content: 'amount=9');
        $response = self::$kernel->handle($request);

        self::assertSame(200, $response->getStatusCode(), $response->getContent());
        self::assertSame(['id' => 3, 'amount' => 9], json_decode($response->getContent(), true));
    }

    public function testPathVariableIsDocumentedWithItsType(): void
    {
        self::bootKernel();
        [, $doc] = $this->json('/typed-api');

        self::assertSame(['type' => 'integer'], $doc['paths']['/typed/items/{id}']['get']['parameters'][0]['schema']);
    }
}
