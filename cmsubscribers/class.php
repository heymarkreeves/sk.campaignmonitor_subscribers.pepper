<?php
/******************************************************************************
 Pepper

 Developer      : Mark J. Reeves
 Plug-in Name   : CampaignMonitor Subscribers

 [slimkiwi.com/pepper](http://www.slimkiwi.com/pepper)

 ******************************************************************************/
 if (!defined('MINT')) { header('Location:/'); }; // Prevent viewing this file directly

$installPepper = "SK_CMSubscribers";

class SK_CMSubscribers extends Pepper
{
	var $version    = 1; // Displays as 0.01
	var $info       = array
	(
	    'pepperName'    => 'CM Subscribers',
	    'pepperUrl'     => 'http://www.slimkiwi.com/pepper',
	    'pepperDesc'    => 'The CM Subscribers Pepper displays your Campaign Monitor email subscribers over the past 24 hours.',
	    'developerName' => 'Mark J. Reeves',
	    'developerUrl'  => 'http://www.slimkiwi.com/'
	);
	var $panes = array
	(
	    'CM Subscribers' => array
	    (
	        'Refresh'
	    )
	);
	var $prefs = array
	(
	    'apiKey'		=> '',
		'listId'		=> '',
	);
	var $manifest = array
	(
	    /*'visit' => array
	    (
	        'window_width' => "TINYINT(5) NOT NULL"
	    )*/
	);
	/**************************************************************************
	 isCompatible()
	 **************************************************************************/
	function isCompatible()
	{
	    if ($this->Mint->version >= 120)
	    {
	        return array
	        (
	            'isCompatible'  => true
	        );
	    }
	    else
	    {
	        return array
	        (
	            'isCompatible'  => false,
	            'explanation'   => '<p>This Pepper is only compatible with Mint 1.2 and higher.</p>'
	    );
	    }
	}
	/**************************************************************************
	 onDisplay()
	 **************************************************************************/
	function onDisplay($pane, $tab, $column = '', $sort = '')
	{
	    $html = '';
	    switch($pane) 
	    {
	        /* CM Subscribers ***************************************************/
	        case 'CM Subscribers': 
	            switch($tab)
	            {
	                /* Refresh ************************************************/
	                case 'Refresh':
	                    $html .= $this->getHTML_CMSubscribers();
	                    break;
	            }
	            break;
	    }
	    return $html;
	}
	/**************************************************************************
	 onDisplayPreferences()
	 **************************************************************************/
	function onDisplayPreferences() 
	{
		/* Global *************************************************************/
		$apiKey = $this->prefs['apiKey'];
		$listId = $this->prefs['listId'];

		$preferences['Campaign Monitor Account'] = <<<HERE
		<table>
			<tr>
				<td><label>Your Campaign Monitor API Key:</label></td>
			</tr>
			<tr>
				<td><span><input type="text" id="apiKey" name="apiKey" value="{$apiKey}" /></span></td>
			</tr>
			<tr>
				<td><label>Your Campaign Monitor List ID:</label></td>
			</tr>
			<tr>
				<td><span><input type="text" id="listId" name="listId" value="{$listId}" /></span></td>
			</tr>
		</table>
HERE;
		
		return $preferences;
	}

	/**************************************************************************
	 onSavePreferences()
	 **************************************************************************/
	function onSavePreferences() 
	{
		$apiKey = (isset($_POST['apiKey']))?$_POST['apiKey']:false;
		$listId = (isset($_POST['listId']))?$_POST['listId']:false;
		
		if ($this->prefs['apiKey'] != $apiKey)
		{
			$this->prefs['apiKey'] = $apiKey;
		}
		if ($this->prefs['listId'] != $listId)
		{
			$this->prefs['listId'] = $listId;
		}
	}
	
	function getHTML_CMSubscribers()
	{
		$tableData['thead'] = array
		(
		    // display name, CSS class(es) for each column
		    array('value'=>'Name','class'=>''),
		    array('value'=>'Email','class'=>''),
			array('value'=>'Date','class'=>'')
		);
		
		/* NEW: $url = 'http://api.createsend.com/api/api.asmx' */
		//$Host = 'app.campaignmonitor.com';
		$Host = 'api.createsend.com';
		$ApiKey=$this->prefs['apiKey'];
		$ListID=$this->prefs['listId'];
		// For testing:
		//$Date='2000-01-01%2000:00:00';
		$UnixDate=time()-(86400);
		$Date=date('Y-m-d',$UnixDate).'%20'.date('H:i:s');

		//$url = 'http://' . $Host . '/api/api.asmx/Subscribers.GetActive';
		$url = 'http://' . $Host . '/api/api.asmx/Subscribers.GetActive';
		$params =	'ApiKey=' . $ApiKey .
					'&ListId=' . $ListID .
					'&Date=' . $Date;

		$curl_handle = curl_init();
		curl_setopt($curl_handle, CURLOPT_URL, "$url");
		curl_setopt($curl_handle, CURLOPT_POST, 1);
		curl_setopt($curl_handle, CURLOPT_POSTFIELDS, $params);
		curl_setopt($curl_handle, CURLOPT_CONNECTTIMEOUT, 40);
		curl_setopt($curl_handle, CURLOPT_TIMEOUT, 40);
		curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, 1);

		$buffer = curl_exec($curl_handle);
		$header  = curl_getinfo($curl_handle);
		curl_close($curl_handle);
		
		$subsXmlDoc = domxml_open_mem($buffer);
		$subsXpath = xpath_new_context($subsXmlDoc);
		$subsResult = xpath_eval($subsXpath, "/anyType/Subscriber");
		
		$subscribers = preg_split("(\</Subscriber>)",$buffer);

		for ($i=0;$i<count($subscribers)-1;$i++)
		{
			$emailArrRight = preg_split("(\<EmailAddress>)",$subscribers[$i]);
			$emailArrLeft = preg_split("(\</EmailAddress>)",$emailArrRight[1]);
			$email = $emailArrLeft[0];

			$nameArrRight = preg_split("(\<Name>)",$subscribers[$i]);
			$nameArrLeft = preg_split("(\</Name>)",$nameArrRight[1]);
			$name = $nameArrLeft[0];

			$dateArrRight = preg_split("(\<Date>)",$subscribers[$i]);
			$dateArrLeft = preg_split("(\</Date>)",$dateArrRight[1]);
			$date = $dateArrLeft[0];
			
			$row = array
			(
				$name,$email,$date
			);
			
			$tableData['tbody'][] = $row;
		}

		$html .= $this->Mint->generateTable($tableData);
		return $html;
		
		/*100: Invalid API Key
		The API key passed was not valid or has expired.
		101: Invalid ListID
		The ListID value passed in was not valid.*/
	}
}