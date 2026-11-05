<?php
/**
 * Announcement Bar Pro — text / HTML / URL sanitizer.
 * Shared by admin (on save) and catalog (on render, defence in depth).
 * Load: $this->load->library('announcementbarpro/sanitizer'); → $this->sanitizer
 * @version 2.0.1
 */
namespace Announcementbarpro;

class Sanitizer {
	/** Allowed inline tags. Everything else is unwrapped (content kept) or dropped. */
	const ALLOWED_TAGS = array('b', 'strong', 'i', 'em', 'u', 's', 'br', 'span', 'a');

	/** Tags removed together with their content. */
	const DROP_TAGS = array('script', 'style', 'iframe', 'object', 'embed', 'noscript', 'template', 'svg', 'math', 'form', 'input', 'button', 'textarea', 'select', 'link', 'meta', 'base', 'head', 'title');

	public function __construct($registry = null) {
	}

	/**
	 * Decodes OpenCart's request encoding (Request class runs htmlspecialchars on all input).
	 */
	public function decode($value) {
		return $this->utf8(html_entity_decode((string)$value, ENT_QUOTES, 'UTF-8'));
	}

	/**
	 * Drops invalid UTF-8 byte sequences (otherwise /u regexes return null and text silently vanishes).
	 */
	public function utf8($value) {
		$value = (string)$value;

		if (function_exists('mb_check_encoding') && !mb_check_encoding($value, 'UTF-8')) {
			$value = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
		}

		return $value;
	}

	/**
	 * OpenCart's mysqli driver uses the 3-byte "utf8" charset, so emoji and other characters above
	 * U+FFFF are stored as "?". Store them as numeric entities instead; decode() restores them.
	 */
	public function encode4byte($value) {
		return (string)preg_replace_callback('/[\x{10000}-\x{10FFFF}]/u', function ($m) {
			return '&#x' . strtoupper(dechex($this->codepoint($m[0]))) . ';';
		}, $this->utf8($value));
	}

	private function codepoint($char) {
		if (function_exists('mb_ord')) {
			return mb_ord($char, 'UTF-8');
		}

		$c = unpack('N', mb_convert_encoding($char, 'UCS-4BE', 'UTF-8'));

		return $c[1];
	}

	/**
	 * Plain text: no tags, no control chars, collapsed whitespace.
	 */
	public function plain($value) {
		$value = strip_tags($this->utf8($value));
		$value = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value);

		return trim((string)preg_replace('/\s+/u', ' ', (string)$value));
	}

	/**
	 * Whitelist HTML: only ALLOWED_TAGS, no attributes except a[href,target]; href scheme checked.
	 */
	public function html($value) {
		$value = trim((string)preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/u', '', $this->utf8($value)));

		if ($value === '') {
			return '';
		}

		if (strpos($value, '<') === false) {
			// No tags: stored value may already contain entities (&amp;, &#x1F69A;) — decode, then escape once.
			return htmlspecialchars($this->decode($value), ENT_QUOTES, 'UTF-8');
		}

		if (!class_exists('DOMDocument')) {
			return htmlspecialchars($this->plain($value), ENT_QUOTES, 'UTF-8');
		}

		$doc = new \DOMDocument('1.0', 'UTF-8');
		$prev = libxml_use_internal_errors(true);
		$doc->loadHTML('<?xml encoding="utf-8"?><div id="abp-root">' . $value . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
		libxml_clear_errors();
		libxml_use_internal_errors($prev);

		$root = null;
		foreach ($doc->getElementsByTagName('div') as $div) {
			if ($div->getAttribute('id') === 'abp-root') {
				$root = $div;
				break;
			}
		}

		if (!$root) {
			return htmlspecialchars($this->plain($value), ENT_QUOTES, 'UTF-8');
		}

		$this->cleanNode($root);

		$html = '';
		foreach ($root->childNodes as $child) {
			$html .= $doc->saveHTML($child);
		}

		return trim($html);
	}

	/**
	 * Safe HTML for output. $allow_html=false → escaped plain text.
	 * Stored text may hold entities (HTML mode, encode4byte), so plain mode decodes before escaping.
	 */
	public function toSafeHtml($value, $allow_html) {
		if ($allow_html) {
			return $this->html($value);
		}

		return htmlspecialchars($this->plain($this->decode($value)), ENT_QUOTES, 'UTF-8');
	}

	/**
	 * Replaces the first {token} that is in text (never inside a tag/attribute) with $replacement.
	 * Remaining tokens in text are removed. Returns array(html, replaced?).
	 */
	public function replaceToken($html, $token, $replacement) {
		$done = false;

		$html = preg_replace_callback('/(<[^>]*>)|' . preg_quote($token, '/') . '/', function ($m) use (&$done, $replacement) {
			if (!empty($m[1])) {
				return $m[1];
			}

			if (!$done && $replacement !== '') {
				$done = true;
				return $replacement;
			}

			return '';
		}, (string)$html);

		return array((string)$html, $done);
	}

	/**
	 * Accepts http(s) absolute URLs, site-relative "/path" and "index.php?route=...". Returns '' if invalid.
	 */
	public function url($value) {
		$value = trim(str_replace(array("\r", "\n", "\t", "\0", ' '), array('', '', '', '', '%20'), $this->utf8($value)));

		// Non-ASCII paths/slugs (e.g. /akcija-šodien) → percent-encoded, so they validate and stay safe.
		$value = (string)preg_replace_callback('/[^\x00-\x7F]+/u', function ($m) {
			return rawurlencode($m[0]);
		}, $value);

		if ($value === '' || strlen($value) > 255) {
			return '';
		}

		if (preg_match('#^https?://#i', $value)) {
			return filter_var($value, FILTER_VALIDATE_URL) !== false ? $value : '';
		}

		if (preg_match('#^/(?!/)#', $value) || stripos($value, 'index.php?') === 0) {
			return preg_match('/^[A-Za-z0-9\-._~:\/?#\[\]@!$&\'()*+,;=%]+$/', $value) ? $value : '';
		}

		return '';
	}

	public function color($value, $default = '') {
		return (is_string($value) && preg_match('/^#[0-9a-fA-F]{6}$/', $value)) ? strtolower($value) : $default;
	}

	/** Font Awesome 4 icon class, e.g. "fa-truck". */
	public function icon($value) {
		$value = trim((string)$value);

		return preg_match('/^fa-[a-z0-9-]{1,40}$/', $value) ? $value : '';
	}

	private function cleanNode(\DOMNode $node) {
		// Copy list first: we mutate while iterating.
		$children = array();
		foreach ($node->childNodes as $child) {
			$children[] = $child;
		}

		foreach ($children as $child) {
			if ($child instanceof \DOMElement) {
				$tag = strtolower($child->nodeName);

				if (in_array($tag, self::DROP_TAGS, true)) {
					$node->removeChild($child);
					continue;
				}

				$this->cleanNode($child);

				if (!in_array($tag, self::ALLOWED_TAGS, true)) {
					while ($child->firstChild) {
						$node->insertBefore($child->firstChild, $child);
					}
					$node->removeChild($child);
					continue;
				}

				$href   = $tag === 'a' ? $this->url($child->getAttribute('href')) : '';
				$target = $tag === 'a' && $child->getAttribute('target') === '_blank';

				while ($child->attributes->length) {
					$child->removeAttribute($child->attributes->item(0)->nodeName);
				}

				if ($tag === 'a') {
					if ($href === '') {
						while ($child->firstChild) {
							$node->insertBefore($child->firstChild, $child);
						}
						$node->removeChild($child);
						continue;
					}

					$child->setAttribute('href', $href);

					if ($target) {
						$child->setAttribute('target', '_blank');
						$child->setAttribute('rel', 'noopener noreferrer');
					}
				}
			} elseif ($child instanceof \DOMComment || $child instanceof \DOMProcessingInstruction || $child instanceof \DOMCdataSection) {
				$node->removeChild($child);
			}
		}
	}
}
