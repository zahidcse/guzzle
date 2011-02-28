<?php
/**
 * @package Guzzle PHP <http://www.guzzlephp.org>
 * @license See the LICENSE file that was distributed with this source code.
 */

namespace Guzzle\Tests\Http;

use Guzzle\Http\EntityBody;

/**
 * @author Michael Dowling <michael@guzzlephp.org>
 */
class EntityBodyTest extends \Guzzle\Tests\GuzzleTestCase
{
    /**
     * @covers Guzzle\Http\EntityBody::factory
     * @expectedException Guzzle\Http\HttpException
     */
    public function testFactoryThrowsException()
    {
        $body = EntityBody::factory(false);
    }

    /**
     * @covers Guzzle\Http\EntityBody::factory
     */
    public function testFactory()
    {
        $body = EntityBody::factory('data');
        $this->assertEquals('data', (string)$body);
        $this->assertEquals(4, $body->getContentLength());
        $this->assertEquals('php', $body->getWrapper());
        $this->assertEquals('temp', $body->getStreamType());

        $handle = fopen(__DIR__ . '/../phpunit.xml', 'r');
        if (!$handle) {
            $this->fail('Could not open test file');
        }
        $body = EntityBody::factory($handle);
        $this->assertEquals(__DIR__ . '/../phpunit.xml', $body->getUri());
        $this->assertTrue($body->isLocal());
        $this->assertEquals(__DIR__ . '/../phpunit.xml', $body->getUri());
        $this->assertEquals(filesize(__DIR__ . '/../phpunit.xml'), $body->getContentLength());

        // make sure that a body will return as the same object
        $this->assertTrue($body === EntityBody::factory($body));
    }

    /**
     * @covers Guzzle\Http\EntityBody::shouldCompress
     */
    public function testDeterminesIfTheBodyShouldBeCompress()
    {
        $this->assertTrue(EntityBody::shouldCompress('test.txt'));
        $this->assertTrue(EntityBody::shouldCompress('.txt'));
        $this->assertFalse(EntityBody::shouldCompress('test.txt.jpg'));
        $this->assertFalse(EntityBody::shouldCompress('test.txtjpg'));
        $this->assertFalse(EntityBody::shouldCompress('test'));
        $this->assertFalse(EntityBody::shouldCompress('test.gz'));
    }

    /**
     * @covers Guzzle\Http\EntityBody::compress
     * @covers Guzzle\Http\EntityBody::uncompress
     * @covers Guzzle\Http\EntityBody::getContentEncoding
     * @covers Guzzle\Http\EntityBody::setStreamFilterContentEncoding
     * @covers Guzzle\Http\EntityBody::handleCompression
     * @covers Guzzle\Http\EntityBody::getContentLength
     */
    public function testHandlesCompression()
    {
        $body = EntityBody::factory('testing 123...testing 123');
        $this->assertFalse($body->getContentEncoding(), '-> getContentEncoding() must initially return FALSE');
        $size = $body->getContentLength();
        $body->compress();
        $this->assertEquals('gzip', $body->getContentEncoding(), '-> getContentEncoding() must return the correct encoding after compressing');
        $this->assertEquals(gzdeflate('testing 123...testing 123'), (string)$body);
        $this->assertTrue($body->getContentLength() < $size);
        $this->assertEquals('testing 123...testing 123', $body->uncompress());
        $this->assertFalse($body->getContentEncoding(), '-> getContentEncoding() must reset to FALSE');

        $this->assertTrue($body->compress('bzip2.compress'));
        $this->assertEquals('compress', $body->getContentEncoding(), '-> compress() must set \'compress\' as the Content-Encoding');
        
        $this->assertFalse($body->compress('non-existent'), '-> compress() must return false when a non-existent stream filter is used');

        // Release the body
        unset($body);

        // Use gzip compression on the initial content.  This will include a 
        // gzip header which will need to be stripped when deflating the stream
        $body = EntityBody::factory(gzencode('test'));
        $this->assertSame($body, $body->setStreamFilterContentEncoding('zlib.deflate'));
        $this->assertTrue($body->uncompress('zlib.inflate'));
        $this->assertEquals('test', (string)$body);
        unset($body);

        // Test using a very long string
        $largeString = '';
        for ($i = 0; $i < 25000; $i++) {
            $largeString .= chr(rand(33, 126));
        }
        $body = EntityBody::factory($largeString);
        $this->assertEquals($largeString, (string)$body);
        $this->assertTrue($body->compress());
        $this->assertNotEquals($largeString, (string)$body);
        $compressed = (string)$body;
        $this->assertTrue($body->uncompress());
        $this->assertEquals($largeString, (string)$body);
        $this->assertEquals($compressed, gzdeflate($largeString));

        $body = EntityBody::factory(fopen(__DIR__ . '/../TestData/compress_test', 'w'));
        $this->assertFalse($body->compress());
        unset($body);

        unlink(__DIR__ . '/../TestData/compress_test');
    }

    /**
     * @covers Guzzle\Http\EntityBody::getContentType
     */
    public function testDeterminesContentType()
    {
        // Test using a string/temp stream
        $body = EntityBody::factory('testing 123...testing 123');
        $this->assertEquals('application/octet-stream', $body->getContentType());

        // Use a local file
        $body = EntityBody::factory(fopen(__FILE__, 'r'));
        $this->assertEquals('text/x-php', $body->getContentType());
    }

    /**
     * @covers Guzzle\Http\EntityBody::getContentMd5
     */
    public function testCreatesMd5Checksum()
    {
        $body = EntityBody::factory('testing 123...testing 123');
        $this->assertEquals(md5('testing 123...testing 123'), $body->getContentMd5());
    }

    /**
     * @covers Guzzle\Http\EntityBody::readChunked
     */
    public function testCanReadUsingChunkedTransferEncoding()
    {
        $body = EntityBody::factory('this is a test of the Emergency Broadcast System (EBS)');
        $this->assertEquals(dechex(3) . "\r\n" . 'thi', $body->readChunked(3));
        $this->assertEquals(dechex(6) . "\r\n" . 's is a', $body->readChunked(6));

        // Jump to a different position in the body (0)
        $this->assertEquals(dechex(3) . "\r\n" . 'thi', $body->readChunked(3, 0));

        // Read the remainder of the entity body
        $this->assertEquals(dechex(51) . "\r\n" . 's is a test of the Emergency Broadcast System (EBS)', $body->readChunked(4096));

        // The last chunk must be 0 length followed by CRLF
        $this->assertEquals(dechex(0) . "\r\n", $body->readChunked(4096));
    }
}