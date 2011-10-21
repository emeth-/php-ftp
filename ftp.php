<?php
/********************************************
 *  Copyright (c) 2011
 *  http://teachthe.net/
 *  Originally developed by Sean Kooyman | teachthe.net(at)gmail.com
 *
 *  License:  GPL version 3.
 *
 *  Permission is hereby granted, free of charge, to any person obtaining a copy
 *  of this software and associated documentation files (the "Software"), to deal
 *  in the Software without restriction, including without limitation the rights
 *  to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 *  copies of the Software, and to permit persons to whom the Software is
 *  furnished to do so, subject to the following conditions:
 *
 *  The above copyright notice and this permission notice shall be included in
 *  all copies or substantial portions of the Software.

 *  THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 *  IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 *  FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 *  AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 *  LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 *  OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 *  THE SOFTWARE.
*********************************************/

global $ftp_server, $ftp_user_name, $ftp_user_pass, $conn_id;

error_reporting(0);

// set up basic connection
$conn_id = ftp_connect($ftp_server) or die("Unable to connect to $ftp_server. Possible bad username or password. Tap the X to try again.");

// login with username and password
$login_result = ftp_login($conn_id, $ftp_user_name, $ftp_user_pass) or die("Unable to connect to $ftp_server. Possible bad username or password. Tap the X to try again.");


if(!$login_result)
{
	print "error";
	exit;
}


function create_directory($makedir, $conn_id)
{
	if (ftp_mkdir($conn_id, $makedir))
	{
		return 1;
	}
	else
	{
		return 0;
	}
}

function create_file($filename, $uploaddir, $conn_id)
{
	if($uploaddir){$uploaddir=$uploaddir.'/';}

	$tmpname = md5($filename.$uploaddir.$conn_id);
  	$fp = fopen("tmp/".$tmpname, "w");
	fwrite($fp, '');
	fclose($fp);

	if (ftp_put($conn_id, $uploaddir.$filename, "tmp/".$tmpname, FTP_BINARY))
	{
		unlink("tmp/".$tmpname);
		return 1;
	}
	else
	{
		unlink("tmp/".$tmpname);
		return 0;
	}
}

//upload file sent to script to FTP
function upload_file($filecont, $backup, $filename, $uploaddir, $conn_id)
{
	if($uploaddir){$uploaddir=$uploaddir.'/';}

	$tmpname = md5($filename.$filecont.$uploaddir.$conn_id);
  	$fp = fopen("tmp/".$tmpname, "w");
	fwrite($fp, $filecont);
	fclose($fp);

	if($backup)
	{
		$newname = $filename.".old.".date("Ymd");
		ftp_rename($conn_id,$uploaddir.$filename,$uploaddir.$newname);
	}
	if (ftp_put($conn_id, $uploaddir.$filename, "tmp/".$tmpname, FTP_BINARY))
	{
		unlink("tmp/".$tmpname);
		return 1;
	}
	else
	{
		unlink("tmp/".$tmpname);
		return 0;
	}
}

function delete_file($directory, $filename, $conn_id)
{

	if (ftp_delete($conn_id, $directory.'/'.$filename))
	{
		return 1;
	}
	else
	{
		return 0;
	}

}

function rename_file($directory, $currfilename, $newfilename, $conn_id)
{
	if($directory){$directory=$directory.'/';}
	ftp_rename($conn_id,$directory.$currfilename,$directory.$newfilename);
	return 1;
}

function move_file($olddirectory, $newdirectory, $filename, $conn_id)
{
	if($olddirectory){$olddirectory=$olddirectory.'/';}
	if($newdirectory){$newdirectory=$newdirectory.'/';}
	ftp_rename($conn_id,$olddirectory.$filename,$newdirectory.$filename);
	return 1;
}
//show contents of file specified
function show_file($file, $conn_id)
{
	$basefile = basename($file);
	$tempHandle = fopen("php://temp", 'w+'); // create a temporary file on our server
	ftp_fget($conn_id, $tempHandle, "/" . $file,FTP_BINARY);
	rewind($tempHandle);
	$text =  stream_get_contents($tempHandle);
	fclose($tempHandle);
	return $text;
}

//Retrieve current file permissions
function get_chmod_file($file, $conn_id)
{
	$fdata = ftp_raw($conn_id, "STAT ".$file); //returns lots of data concerning file
	$fdata2 = explode(" ", $fdata[1]);
	$fdatastr = substr($fdata2[0], -9);
	$fiperm = permStrToNum($fdatastr); //strips down data to just 'rwxrwxrwx' or similar permission string
	return $fiperm;
}

//Set new file permissions
function set_chmod_file($file, $permissions, $conn_id)
{
	$permissions = octdec ( str_pad ( $permissions, 4, '0', STR_PAD_LEFT ) );
	$permissions = (int) $permissions;
	if (ftp_chmod($conn_id, $permissions, $file) !== false)
	{
		return 1;
	}
	else
	{
		return 0;
	}
}


function file_info($fullfile, $conn_id)
{
	$fnp = explode("/",$fullfile);
	$si = count($fnp)-1;
	$title = $fnp[$si];
	$filetype = ext_to_type(strtolower($title));
	$size = ftp_size($conn_id,$fullfile);
	$date = ftp_mdtm($conn_id,$fullfile);

	$fdata = ftp_raw($conn_id, "STAT ".$fullfile); //returns lots of data concerning file
	$fdata2 = explode(" ", $fdata[1]);
	$fdatastr = substr($fdata2[0], -9);
	$fiperm = permStrToNum($fdatastr); //strips down data to just 'rwxrwxrwx' or similar permission string

	$temparr = array();
	$temparr['title']=$title;
	$temparr['ftype']=$filetype;
	$temparr['fsize']=$size;
	$temparr['fdate']=$date;
	$temparr['fperm']=$fiperm;
	return $temparr;
}



//email specified file to the specified email address
function email_file($file, $email, $ftp_server, $ftp_user_name, $ftp_user_pass)
{
	$fileatt = "ftp://{$ftp_user_name}:{$ftp_user_pass}@{$ftp_server}/{$file}"; // Path to the file
	$fileatt_type = "application/octet-stream"; // File Type
	$fileatt_name = basename($file); // Filename that will be used for the file as the attachment

	$email_from = $email; // Who the email is from
	$email_subject = basename($file)." sent via OnlineFTP from ftp://".$ftp_server; // The Subject of the email
	$email_message = "You have been sent a file from OnlineFTP. Please see the file attached to this message.\n\n - \nFor more information on OnlineFTP, please visit http://seanybob.net/onlineftp"; // Message that the email has in it

	$email_to = $email; // Who the email is too

	$headers = "From: ".$email_from;

	$data = file_get_contents($fileatt);

	$semi_rand = md5(time());
	$mime_boundary = "==Multipart_Boundary_x{$semi_rand}x";

	$headers .= "\nMIME-Version: 1.0\n" .
	"Content-Type: multipart/mixed;\n" .
	" boundary=\"{$mime_boundary}\"";

	$email_message .= "This is a multi-part message in MIME format.\n\n" .
	"--{$mime_boundary}\n" .
	"Content-Type:text/html; charset=\"iso-8859-1\"\n" .
	"Content-Transfer-Encoding: 7bit\n\n" .
	$email_message . "\n\n";

	$data = chunk_split(base64_encode($data));

	$email_message .= "--{$mime_boundary}\n" .
	"Content-Type: {$fileatt_type};\n" .
	" name=\"{$fileatt_name}\"\n" .
	//"Content-Disposition: attachment;\n" .
	//" filename=\"{$fileatt_name}\"\n" .
	"Content-Transfer-Encoding: base64\n\n" .
	$data . "\n\n" .
	"--{$mime_boundary}--\n";

	$ok = @mail($email_to, $email_subject, $email_message, $headers);

	if($ok)
		return 1;
	else
		return 0;
}


//show contents of specified directory
function show_directory($path, $conn_id)
{

	if($path)
	{
		$path = $path."/";			//if directory specified, append a slash
	}
	if($path == '') { $path = "."; }

	// get contents of the current directory
	$contents = ftp_nlist($conn_id, $path);

	// output $contents
	asort($contents);
	if($path != ".")
	{
		$parentDirectory = preg_replace("/\/([^\/]*)$/","",$path);
	}
	$returnarr = array();
	foreach($contents AS $thisdirectory)
	{
		if($path != ".")
		{
			$thisdirectory = $path.basename($thisdirectory);
		}

		$title = preg_replace("/.*\//","",$thisdirectory);

		$fisize = ftp_size($conn_id,$thisdirectory);
		if($fisize == -1)
		{
			$temparr = array();
			$temparr['title']=$title;
			$temparr['ftype']='directory';
			$returnarr[]=$temparr;
		}
		else
		{
			$fidate = ftp_mdtm($conn_id,$thisdirectory);

			$fdata = ftp_raw($conn_id, "STAT ".$thisdirectory); //returns lots of data concerning file
			$fdata2 = explode(" ", $fdata[1]);
			$fdatastr = substr($fdata2[0], -9);
			$fiperm = permStrToNum($fdatastr); //strips down data to just 'rwxrwxrwx' or similar permission string

			$filetype = ext_to_type(strtolower($title));
			$fisize = bytesConvert($fisize);
			$temparr = array();
			$temparr['title']=$title;
			$temparr['ftype']=$filetype;
			$temparr['fsize']=$fisize;
			$temparr['fdate']=$fidate;
			$temparr['fperm']=$fiperm;
			$returnarr[]=$temparr;
		}
		//print "</li>"; //$thisdirectory.
	}
	return $returnarr;
}	//END show_directory()

function bytesConvert($bytes)
{
    $ext = array('B', 'kB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB');
    $unitCount = 0;
    for(; $bytes > 1024; $unitCount++) $bytes /= 1024;
    return number_format($bytes) ." ". $ext[$unitCount];
}

function permStrToNum($str)
{
	//break down permissions into individual letters
	$char1 = substr($str, 0, 1);
	$char2 = substr($str, 1, 1);
	$char3 = substr($str, 2, 1);
	$char4 = substr($str, 3, 1);
	$char5 = substr($str, 4, 1);
	$char6 = substr($str, 5, 1);
	$char7 = substr($str, 6, 1);
	$char8 = substr($str, 7, 1);
	$char9 = substr($str, 8, 1);

	//prepare final characters
	$endchar1 = 0;
	$endchar2 = 0;
	$endchar3 = 0;

	//get each digit of the final perm
	if($char1 == 'r'){$endchar1+=4;}
	if($char2 == 'w'){$endchar1+=2;}
	if($char3 == 'x'){$endchar1+=1;}

	if($char4 == 'r'){$endchar2+=4;}
	if($char5 == 'w'){$endchar2+=2;}
	if($char6 == 'x'){$endchar2+=1;}

	if($char7 == 'r'){$endchar3+=4;}
	if($char8 == 'w'){$endchar3+=2;}
	if($char9 == 'x'){$endchar3+=1;}

	//concatenate each individual character into final form (7, 7, and 7 into 777)
	$str = $endchar1.$endchar2.$endchar3;

	return $str;
}

function ext_to_type($title)
{
	if(preg_match("/\.html$/",$title) || preg_match("/\.php$/",$title) || preg_match("/\.asp$/",$title))
	{
		$filetype = "htmlFile";
	}
	else if(preg_match("/\.zip$/",$title) || preg_match("/\.rar$/",$title) || preg_match("/\.7z$/",$title) || preg_match("/\.ace$/",$title))
	{
		$filetype = "zipFile";
	}
	else if(preg_match("/\.jpg$/",$title) || preg_match("/\.jpeg$/",$title) || preg_match("/\.gif$/",$title) || preg_match("/\.png$/",$title))
	{
		$filetype = "imageFile";
	}
	else if(preg_match("/\.pdf$/",$title))
	{
		$filetype = "pdfFile";
	}
	else if(preg_match("/\.rtf$/",$title) || preg_match("/\.doc$/",$title) || preg_match("/\.docx$/",$title) || preg_match("/\.odt$/",$title))
	{
		$filetype = "richTextFile";
	}
	else if(preg_match("/\.bat$/",$title) || preg_match("/\.exe$/",$title) || preg_match("/\.app$/",$title) || preg_match("/\.deb$/",$title))
	{
		$filetype = "exeFile";
	}
	else if(preg_match("/\.txt$/",$title))
	{
		$filetype = "textFile";
	}
	else
	{
		$filetype = "plain";
	}
	return $filetype;
}

?>