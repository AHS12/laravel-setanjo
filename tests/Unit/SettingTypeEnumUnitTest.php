<?php

namespace Ahs12\Setanjo\Tests\Unit;

use Ahs12\Setanjo\Enums\SettingType;

describe('SettingType Enum', function () {
    it('has all expected cases', function () {
        $cases = SettingType::cases();
        $values = array_map(fn ($case) => $case->value, $cases);

        expect($values)->toContain('string', 'integer', 'float', 'boolean', 'array', 'json', 'object');
    });

    it('creates from string value', function () {
        expect(SettingType::fromValue('test'))->toBe(SettingType::STRING);
        expect(SettingType::fromValue(''))->toBe(SettingType::STRING);
    });

    it('creates from integer value', function () {
        expect(SettingType::fromValue(123))->toBe(SettingType::INTEGER);
        expect(SettingType::fromValue(0))->toBe(SettingType::INTEGER);
        expect(SettingType::fromValue(-123))->toBe(SettingType::INTEGER);
    });

    it('creates from float value', function () {
        expect(SettingType::fromValue(12.34))->toBe(SettingType::FLOAT);
        expect(SettingType::fromValue(0.0))->toBe(SettingType::FLOAT);
        expect(SettingType::fromValue(-12.34))->toBe(SettingType::FLOAT);
    });

    it('creates from boolean value', function () {
        expect(SettingType::fromValue(true))->toBe(SettingType::BOOLEAN);
        expect(SettingType::fromValue(false))->toBe(SettingType::BOOLEAN);
    });

    it('creates from array value', function () {
        expect(SettingType::fromValue([]))->toBe(SettingType::ARRAY);
        expect(SettingType::fromValue(['key' => 'value']))->toBe(SettingType::ARRAY);
    });

    it('creates from object value', function () {
        expect(SettingType::fromValue((object) ['key' => 'value']))->toBe(SettingType::OBJECT);
    });

    it('creates from null value', function () {
        expect(SettingType::fromValue(null))->toBe(SettingType::STRING);
    });

    // JSON-specific tests
    it('creates from valid JSON string', function () {
        expect(SettingType::fromValue('{"key": "value"}'))->toBe(SettingType::JSON);
        expect(SettingType::fromValue('["item1", "item2"]'))->toBe(SettingType::JSON);
        expect(SettingType::fromValue('true'))->toBe(SettingType::JSON);
        expect(SettingType::fromValue('false'))->toBe(SettingType::JSON);
        expect(SettingType::fromValue('null'))->toBe(SettingType::JSON);
        expect(SettingType::fromValue('123'))->toBe(SettingType::JSON);
        expect(SettingType::fromValue('12.34'))->toBe(SettingType::JSON);
        expect(SettingType::fromValue('"string value"'))->toBe(SettingType::JSON);
    });

    it('creates from complex JSON structures', function () {
        $complexJson = json_encode([
            'users' => [
                ['name' => 'John', 'age' => 30],
                ['name' => 'Jane', 'age' => 25],
            ],
            'settings' => [
                'theme' => 'dark',
                'notifications' => true,
            ],
        ]);

        expect(SettingType::fromValue($complexJson))->toBe(SettingType::JSON);
    });

    it('does not detect invalid JSON as JSON type', function () {
        expect(SettingType::fromValue('{"invalid": json}'))->toBe(SettingType::STRING);
        expect(SettingType::fromValue('[invalid, json]'))->toBe(SettingType::STRING);
        expect(SettingType::fromValue('invalid json string'))->toBe(SettingType::STRING);
        expect(SettingType::fromValue('{incomplete'))->toBe(SettingType::STRING);
    });

    it('handles edge cases for JSON detection', function () {
        // Empty string should be STRING, not JSON
        expect(SettingType::fromValue(''))->toBe(SettingType::STRING);

        // Whitespace only should be STRING
        expect(SettingType::fromValue('   '))->toBe(SettingType::STRING);

        // Single characters that could be confused
        expect(SettingType::fromValue('{'))->toBe(SettingType::STRING);
        expect(SettingType::fromValue('}'))->toBe(SettingType::STRING);
        expect(SettingType::fromValue('['))->toBe(SettingType::STRING);
        expect(SettingType::fromValue(']'))->toBe(SettingType::STRING);

        // Valid JSON with whitespace
        expect(SettingType::fromValue('  {"key": "value"}  '))->toBe(SettingType::JSON);
    });

    it('correctly identifies JSON vs regular strings', function () {
        // These should be JSON
        expect(SettingType::fromValue('{}'))->toBe(SettingType::JSON);
        expect(SettingType::fromValue('[]'))->toBe(SettingType::JSON);
        expect(SettingType::fromValue('0'))->toBe(SettingType::JSON);

        // These should be STRING
        expect(SettingType::fromValue('hello world'))->toBe(SettingType::STRING);
        expect(SettingType::fromValue('not-json'))->toBe(SettingType::STRING);
        expect(SettingType::fromValue('123abc'))->toBe(SettingType::STRING);
    });
});
