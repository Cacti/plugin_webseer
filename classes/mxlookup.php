<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

class mxlookup {
	var $dns_socket = null;
	var $QNAME      = '';
	var $dns_packet = null;
	var $ANCOUNT    = 0;
	var $cIx        = 0;
	var $arrMX      = [];
	var $dns_repl_domain;

	/**
	 * Performs a raw UDP DNS MX record lookup for a domain against a DNS
	 * server, building and sending the query packet, then parsing the
	 * reply into a list of resolved IP addresses. Called when a new
	 * mxlookup object is constructed, e.g. from this plugin's 'dns' type
	 * service checks.
	 *
	 * @param string $domain The domain name to look up MX records for.
	 * @param string $dns    The DNS server IP address to query; defaults
	 *                       to '4.2.2.1'.
	 *
	 * @return void
	 */
	function __construct($domain, $dns = '4.2.2.1') {
		$this->QNAME($domain);
		$this->pack_dns_packet();

		$dns_socket = fsockopen("udp://$dns", 53);

		fwrite($dns_socket, $this->dns_packet, strlen($this->dns_packet));

		$this->dns_reply  = fread($dns_socket,1);
		$bytes            = stream_get_meta_data($dns_socket);
		$this->dns_reply .= fread($dns_socket,$bytes['unread_bytes']);

		fclose($dns_socket);

		$this->cIx       = 6;
		$this->ANCOUNT   = $this->gord(2);
		$this->cIx      += 4;

		$this->parse_data($this->dns_repl_domain);

		$this->cIx      += 7;

		for ($ic = 1; $ic <= $this->ANCOUNT; $ic++) {
			$QTYPE = ord($this->gdi($this->cIx));

			if ($QTYPE !== 1) {
				print('[Record not returned]');
				die();
			}

			$this->cIx += 8;

			$ip            = ord($this->gdi($this->cIx)) . '.' . ord($this->gdi($this->cIx)) . '.' . ord($this->gdi($this->cIx)) . '.' . ord($this->gdi($this->cIx));
			$this->arrMX[] = $ip;

			// $mxPref = ord($this->gdi($this->cIx));
			// $this->parse_data($curmx);
			// $this->arrMX[] = array('MX_Pref' => $mxPref, 'MX' => $curmx);
			// $this->cIx += 3;
		}
	}

	/**
	 * No-op destructor. Invoked automatically by PHP when the object is
	 * destroyed.
	 *
	 * @return bool Always true.
	 */
	function __destruct() {
		return true;
	}

	/**
	 * Parses a DNS-encoded domain name (including compressed/pointer-
	 * referenced labels) from the current position in the reply buffer,
	 * recursing when a compression pointer is encountered. Called from the
	 * constructor to decode the query name and each answer record's name.
	 *
	 * @param string $retval Reference, set to the decoded dot-separated
	 *                       domain name.
	 *
	 * @return void
	 */
	function parse_data(&$retval) {
		$arName = [];
		$byte   = ord($this->gdi($this->cIx));

		while ($byte !== 0) {
			if ($byte == 192) { // compressed
				$tmpIx     = $this->cIx;
				$this->cIx = ord($this->gdi($cIx));
				$tmpName   = $retval;
				$this->parse_data($tmpName);
				$retval    = $retval . '.' . $tmpName;
				$this->cIx = $tmpIx + 1;

				return;
			}

			$retval = '';
			$bCount = $byte;

			for ($b = 0; $b < $bCount; $b++) {
				$retval .= $this->gdi($this->cIx);
			}

			$arName[] = $retval;
			$byte     = ord($this->gdi($this->cIx));
		}

		$retval = join('.',$arName);
	}

	/**
	 * Reads a single byte (or run of bytes) from the DNS reply buffer at
	 * the current cursor position, advancing the cursor by one. Called
	 * throughout this class while parsing the raw DNS reply.
	 *
	 * @param int $cIx   Unused; the buffer position actually read from is
	 *                   $this->cIx (this parameter is accepted for
	 *                   historical/reference-signature reasons but is not
	 *                   itself consulted).
	 * @param int $bytes The number of bytes to read; defaults to 1.
	 *
	 * @return string The raw byte(s) read from the buffer.
	 */
	function gdi(&$cIx,$bytes = 1) {
		$this->cIx++;

		return (substr($this->dns_reply, $this->cIx - 1, $bytes));
	}

	/**
	 * Encodes a domain name into DNS query label format (length-prefixed
	 * labels terminated by a zero byte) and appends it to $this->QNAME.
	 * Called from the constructor to build the outgoing query packet.
	 *
	 * @param string $domain The domain name to encode.
	 *
	 * @return void
	 */
	function QNAME($domain) {
		$dot_pos = 0;
		$temp    = '';

		while ($dot_pos = strpos($domain, '.')) {
			$temp         = substr($domain, 0, $dot_pos);
			$domain       = substr($domain, $dot_pos + 1);
			$this->QNAME .= chr(strlen($temp)) . $temp;
		}

		$this->QNAME .= chr(strlen($domain)) . $domain . chr(0);
	}

	/**
	 * Reads a run of bytes from the DNS reply buffer at the current cursor
	 * position and returns their combined ordinal (numeric byte) values,
	 * advancing the cursor by the number of bytes read. Called from the
	 * constructor to read the ANCOUNT field of the DNS reply header.
	 *
	 * @param int $ln The number of bytes to read; defaults to 1.
	 *
	 * @return string The concatenated ordinal values of the bytes read.
	 */
	function gord($ln = 1) {
		$reply = '';

		for ($i = 0; $i < $ln; $i++) {
			$reply .= ord(substr($this->dns_reply, $this->cIx, 1));
			$this->cIx++;
		}

		return $reply;
	}

	/**
	 * Builds the raw outgoing DNS query packet (header flags/counts plus
	 * the encoded question name and type/class) for an MX record query,
	 * storing it on $this->dns_packet. Called from the constructor before
	 * sending the query.
	 *
	 * @return void
	 */
	function pack_dns_packet() {
		$this->dns_packet =
			chr(0) . chr(1) .
			chr(1) . chr(0) .
			chr(0) . chr(1) .
			chr(0) . chr(0) .
			chr(0) . chr(0) .
			chr(0) . chr(0) .
			$this->QNAME .
			chr(0) . chr(1) .
			chr(0) . chr(1);
	}
}
