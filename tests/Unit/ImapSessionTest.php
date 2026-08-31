<?php

namespace Tests\Unit;

use App\Services\Mail\ImapSession;
use PHPUnit\Framework\TestCase;

class ImapSessionTest extends TestCase
{
    public function test_it_parses_capabilities_and_folder_listing(): void
    {
        [$client, $server] = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        fwrite($server, "* CAPABILITY IMAP4rev1 IDLE UIDPLUS\r\nA0001 OK CAPABILITY completed\r\n");
        fwrite($server, "* LIST (\\HasNoChildren \\Inbox) \"/\" \"INBOX\"\r\n* LIST (\\HasNoChildren \\Sent) \"/\" \"Sent Items\"\r\nA0002 OK LIST completed\r\n");

        $session = new ImapSession($client);

        $this->assertSame(['IMAP4REV1', 'IDLE', 'UIDPLUS'], $session->capabilities());

        $folders = $session->listMailboxes();
        $this->assertCount(2, $folders);
        $this->assertSame('INBOX', $folders[0]['name']);
        $this->assertSame('/', $folders[0]['delimiter']);
        $this->assertTrue($folders[0]['selectable']);
        $this->assertContains('\\Inbox', $folders[0]['flags']);
        $this->assertSame('Sent Items', $folders[1]['name']);

        fclose($server);
        $session->logout();
    }

    public function test_it_parses_select_status_and_uid_search_results(): void
    {
        [$client, $server] = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        fwrite($server, "* FLAGS (\\Answered \\Flagged \\Deleted \\Seen \\Draft)\r\n* 42 EXISTS\r\n* 1 RECENT\r\n* OK [UNSEEN 7] first unseen\r\n* OK [UIDVALIDITY 12345] valid\r\n* OK [UIDNEXT 99] next\r\n* OK [PERMANENTFLAGS (\\Answered \\Flagged \\Deleted \\Seen \\Draft \\*)] flags\r\nA0001 OK [READ-WRITE] SELECT completed\r\n");
        fwrite($server, "* STATUS \"INBOX\" (MESSAGES 42 UNSEEN 5 UIDNEXT 99 UIDVALIDITY 12345)\r\nA0002 OK STATUS completed\r\n");
        fwrite($server, "* SEARCH 2 5 9 12\r\nA0003 OK SEARCH completed\r\n");

        $session = new ImapSession($client);

        $selected = $session->select('INBOX');
        $this->assertSame(42, $selected['exists']);
        $this->assertSame(1, $selected['recent']);
        $this->assertSame(7, $selected['unseen']);
        $this->assertSame(12345, $selected['uid_validity']);
        $this->assertSame(99, $selected['uid_next']);
        $this->assertContains('\\Seen', $selected['flags']);

        $status = $session->status('INBOX');
        $this->assertSame(42, $status['messages']);
        $this->assertSame(5, $status['unseen']);
        $this->assertSame(99, $status['uidnext']);
        $this->assertSame(12345, $status['uidvalidity']);

        $this->assertSame([2, 5, 9, 12], $session->uidSearch('UID 2:*'));

        fclose($server);
        $session->logout();
    }

    public function test_list_parser_marks_noselect_folders(): void
    {
        $stream = fopen('php://temp', 'r+');
        $session = new ImapSession($stream);

        $folder = $session->parseListLine('* LIST (\\Noselect \\HasChildren) "/" "Archive"');

        $this->assertSame('Archive', $folder['name']);
        $this->assertFalse($folder['selectable']);
        $this->assertContains('\\Noselect', $folder['flags']);

        $session->logout();
    }
}
