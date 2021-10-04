<?php

final class PhabricatorDriveEmbedRemarkupRule extends PhutilRemarkupRule {

  public function getPriority() {
    return 350.0;
  }

  public function apply($text) {
    try {
      $uri = new PhutilURI($text);
    } catch (Exception $ex) {
      return $text;
    }

    $text_mode = $this->getEngine()->isTextMode();
    $mail_mode = $this->getEngine()->isHTMLMailMode();

    if ($text_mode || $mail_mode) {
      return $text;
    }

    $domain = $uri->getDomain();
    if (!preg_match('/docs\.google\.com\z/', $domain)) {
      return $text;
    }

    $path = $uri->getPath();
    if (!preg_match('/.*\/(pub|pubhtml|viewform)(\z|\?)/', $path)) {
      return $text;
    }

    $embedded = false;

    $params = $uri->getQueryParamsAsPairList();
    $hasParams = false;
    $outputFound = false;
    foreach ($params as $pair) {
      $hasParams = true;
      list($k, $v) = $pair;
      if ($k === 'embedded' && $v === 'true') {
        $embedded = true;
      }
      if ($k ==='output') {
        // This might be PDF or csv etc, which would trigger a file
        // download. Maybe we could output a nice download button?
        return $text;
      }
    }

    $embedURI = ''.$uri;

    if($embedded === false) {
      if($hasParams === true) {
        $embedURI.="&embedded=true";
      }else {
        $embedURI.="?embedded=true";
      }
    }

    $iframe = $this->newTag(
      'div',
      array(
        'class' => 'embedded-drivedoc',
      ),
      $this->newTag(
        'iframe',
        array(
          'width'       => '650',
          'height'      => '800',
          'style'       => 'margin: 1em auto; border: 1em;',
          'src'         => $embedURI,
          'frameborder' => 0,
        ),
        ''));

    return $this->getEngine()->storeText($iframe);
  }

  public function didMarkupText() {
    CelerityAPI::getStaticResourceResponse()
      ->addContentSecurityPolicyURI('frame-src', 'https://docs.google.com/');
  }

}
