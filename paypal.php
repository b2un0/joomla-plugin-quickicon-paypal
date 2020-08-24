<?php

/**
 * @author     Branko Wilhelm <branko.wilhelm@gmail.com>
 * @link       http://www.z-index.net
 * @copyright  (c) 2014 Branko Wilhelm
 * @license    GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

defined('_JEXEC') or die;

class plgQuickiconPayPal extends JPlugin
{

    private $url = 'https://api-3t.paypal.com/nvp';
    private $success = false;
    private $output = '';

    public function onGetIcons()
    {
        if ($this->params->get('balance') && $this->params->get('apiuser') && $this->params->get('apipw') && $this->params->get('apisig')) {
            $this->getBalance();
        }

        JFactory::getDocument()->addStyleDeclaration('.icon-paypal:before{content: url("' . JUri::root() . 'media/paypal/paypal-icon.png");}');

        if (!empty($this->output)) {
            if (version_compare(JVERSION, '3', '>=')) {
                $class = $this->success ? 'success' : 'important';
                $this->output = '<span class="label label-' . $class . '">' . $this->output . '</span>';
            } else {
                $class = $this->success ? 'success' : 'disabled';
                $this->output = '<small class="' . $class . '">' . $this->output . '</small>';
            }
        }

        return array(
            array(
                'link' => $this->params->get('url', 'http://www.paypal.com'),
                'image' => 'paypal',
                'text' => JText::sprintf('PayPal %s', $this->output),
                'id' => 'plg_quickicon_paypal'
            )
        );
    }

    private function getBalance()
    {
        $cache = JFactory::getCache('paypal', 'output');
        $cache->setCaching(1);
        $cache->setLifeTime($this->params->get('cache', 60) * 60);

        $key = md5($this->params->toString());

        if (!$result = $cache->get($key)) {
            try {
                $http = JHttpFactory::getHttp();

                $data = array(
                    'USER' => $this->params->get('apiuser'),
                    'PWD' => $this->params->get('apipw'),
                    'SIGNATURE' => $this->params->get('apisig'),
                    'VERSION' => '112', // https://developer.paypal.com/webapps/developer/docs/classic/release-notes/
                    'METHOD' => 'GetBalance'
                );

                $result = $http->post($this->url, $data);
            } catch (Exception $e) {
                JFactory::getApplication()->enqueueMessage($e->getMessage());
                return $this->output = JText::_('ERROR');
            }

            $cache->store($result, $key);
        }

        if ($result->code != 200) {
            $msg = __CLASS__ . ' HTTP-Status ' . JHtml::_('link', 'http://wikipedia.org/wiki/List_of_HTTP_status_codes#' . $result->code, $result->code, array('target' => '_blank'));
            JFactory::getApplication()->enqueueMessage($msg, 'error');
            return $this->output = JText::_('ERROR');
        }

        parse_str($result->body, $result->body);

        if (!isset($result->body['ACK']) || $result->body['ACK'] !== 'Success') {
            return $this->output = $result->body['L_SHORTMESSAGE0'];
        }

        $this->success = true;
        $this->output = $result->body['L_AMT0'] . ' ' . $result->body['L_CURRENCYCODE0'];
    }
}
