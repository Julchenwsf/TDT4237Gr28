<?php
namespace tdt4237\webapp;

use Slim\Slim;
use Slim\Views\TwigExtension;
use tdt4237\webapp\Security;

class SecureTwigExtension extends \Twig_Extension {
	public function getName() {
		return 'secureextension';
	}

	public function getFunctions() {
		return array(
			new \Twig_SimpleFunction('secureForm', array($this, 'SecureForm')),
			new \Twig_SimpleFunction('closeSecureForm', array($this, 'CloseSecureForm'))
		);
	}

	public function SecureForm($controller, $fields = [], $method = 'post', $action = '', $attrs='') {
		return sprintf('<form method="%s" %s action="%s"><div style="display:none"><input type="hidden" name="%s" value="%s" /></div>', htmlentities($method), $attrs, htmlentities($action), Security::POST_NAME, Security::getToken($controller, $fields));
	}

	public function CloseSecureForm() {
		return '</form>';
	}
}
