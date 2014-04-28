<?

namespace Disney;

class MailChimp {

	var $key;
	var $list_id;
		
	var $from_email;
	var $from_name;
	var $to_name;

	var $new = 0;
	var $old = 0;
	var $stock = 0;

	var $html = array();

	public function __construct($config) {
		$this->key = $config['key'];
		$this->list_id = $config['list'];

		$this->from_email = $config['from_email'];
		$this->from_name = $config['from_name'];
		$this->to_name = $config['to_name'];
	}
	
	public function add($locale, $html, $new, $old, $stock) {
		$this->html[$locale] = $html;
		$this->new += $new;
		$this->old += $old;
		$this->stock += $stock;
	}

	public function send() {
		$subject = array();
		$send = false;

		if ($this->new > 0) {
			array_push($subject, $this->new . " new items");
			$send = true;
		}

		if ($this->old > 0) {
			array_push($subject, $this->old . " existing items");
			$send = true;
		}

		if ($this->stock > 0) {
			array_push($subject, $this->stock . " in stock");
			$send = true;
		}

		if (!$send) {
			return;
		}

		$subject = implode(", ", $subject);

		$mailchimp = new \Mailchimp($this->key);
		$campaign = new \Mailchimp_Campaigns($mailchimp);
		$campaign_info = $campaign->create('regular', array(
			'list_id' => $this->list_id,
			'subject' => $subject,
			'from_email' => $this->from_email,
			'from_name' => $this->from_name,
			'to_name' => $this->to_name,
			'title' => date('Y-m-d H:i:s'),
			'generate_text' => true,
		), array(
			'html' => $this->html(),
		));

		$campaign->send($campaign_info['id']);
	}

	private function html() {
		$full_html = "";

		foreach ($this->html as $locale => $html) {
			$tag = strtoupper($locale);
			$full_html .= "*|IF:DISNEY_{$tag}=Yes|*
			<b>{$tag}:</b>
			<br/><br/>
			{$html}
			<br/><br/>
			*|END:IF|*";
		}

		$full_html .= '
		<a href="*|ABOUT_LIST|*" target="_blank" style="color:#404040 !important;"><em>why did I get this?</em></a>
		&nbsp;&nbsp;&nbsp;&nbsp;
		<a href="*|UNSUB|*" style="color:#404040 !important;">unsubscribe from this list</a>
		&nbsp;&nbsp;&nbsp;&nbsp;
		<a href="*|UPDATE_PROFILE|*" style="color:#404040 !important;">update subscription preferences</a>
		<br/><br/>
		*|REWARDS|*';

		return $full_html;
	}

}