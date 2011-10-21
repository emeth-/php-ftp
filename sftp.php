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


include('Net/SFTP.php');

$sftp = new Net_SFTP($ftp_server);
if (!$sftp->login($ftp_user_name, $ftp_user_pass))
{
	print "Unable to connect to $ftp_server. Possible bad username or password. Tap the X to try again.";
	exit;
}

function create_file($filename, $uploaddir, $conn_id="")
{

	global $sftp;

	if($uploaddir){$uploaddir=$uploaddir.'/';}

	$filecon = "";


	if($sftp->put($uploaddir.$filename, $filecon))
	{
		return 1;
	}
	else
	{
		return 0;
	}
}

function upload_file($filecont, $backup, $filename, $uploaddir, $conn_id="")
{
	global $sftp;

	if($uploaddir){$uploaddir=$uploaddir.'/';}

	$filecon = $filecont;

	if($backup)
	{
		$newname = $filename.".".$backup.".old.".date("Ymd");
		$sftp->rename($uploaddir.$filename,$uploaddir.$newname);
	}

	if($sftp->put($uploaddir.$filename, $filecon))
	{
		return 1;
	}
	else
	{
		return 0;
	}
}

function create_directory($makedir, $conn_id="")
{
	global $sftp;
	if($sftp->mkdir($makedir))
	{
		return 1;
	}
	else
	{
		return 0;
	}
}


function delete_file($directory, $filename, $conn_id="")
{
	global $sftp;

	if ($sftp->delete($directory.'/'.$filename))
	{
		return 1;
	}
	else
	{
		return 0;
	}

}

function rename_file($directory, $currfilename, $newfilename, $conn_id="")
{
	global $sftp;

	if($directory){$directory=$directory.'/';}

	$newname = $filename.".old.".date("Ymd");
	$sftp->rename($directory.$currfilename,$directory.$newfilename);

	return 1;
}

function move_file($olddirectory, $newdirectory, $filename, $conn_id="")
{
	global $sftp;
	if($olddirectory){$olddirectory=$olddirectory.'/';}
	if($newdirectory){$newdirectory=$newdirectory.'/';}

	$sftp->rename($olddirectory.$filename,$newdirectory.$filename);

	return 1;
}

//show contents of file specified
function show_file($file, $conn_id="")
{
	global $sftp;
	$content = $sftp->get($file);
	$content = str_replace("<?", "<\?", $content);
	$content = str_replace("<%", "<\%", $content);
	return $content;
}

//Retrieve current file permissions
function get_chmod_file($file, $conn_id="")
{
	global $sftp;
	$output = $sftp->getchmod($file);
	$output = decoct($output);
	return substr($output, -3);
}

//Set new file permissions
function set_chmod_file($file, $permissions, $conn_id="")
{
	global $sftp;
	$permissions = octdec ( str_pad ( $permissions, 4, '0', STR_PAD_LEFT ) );
	$permissions = (int) $permissions;
	if($sftp->chmod($permissions, $file))
	{
		return 1;
	}
	else
	{
		return 0;
	}
}


//Returns file size & date modified in pipe delimited string (i.e. 128426952|65135)
function file_info($file, $conn_id="")
{
	/*
	stat returns
	  File: `public_html/join.txt'
	  Size: 2813      	Blocks: 8          IO Block: 4096   regular file
	Device: 807h/2055d	Inode: 87801864    Links: 1
	Access: (0644/-rw-r--r--)  Uid: (  520/seanybob)   Gid: (  516/seanybob)
	Access: 2010-03-06 18:07:30.000000000 -0600
	Modify: 2010-03-06 18:07:31.000000000 -0600
	Change: 2010-03-08 15:13:20.000000000 -0600
	*/

	global $sftp;
	$fdata = $sftp->exec("stat $file");
	$fsize3 = explode("Size:", $fdata);
	$fsize2 = explode("Block", $fsize3[1]);
	$fsize = trim($fsize2[0]);
	$fdate3 = explode("Modify:", $fdata);
	$fdate2 = explode(".", $fdate3[1]);
	$fdate = trim($fdate2[0]);
	if(!$fdate || $fdate==0)
	{
		$fdate = $sftp->mtime($adddir.$thisdirectory);
	}
	else
	{
		$fdate = strtotime($fdate);
	}
	if(!$fsize || $fsize==0)
	{
		$fsize = $sftp->size($file);
	}

	$fnp = explode("/",$file);
	$si = count($fnp)-1;
	$title = $fnp[$si];
	$filetype = ext_to_type(strtolower($title));

	$output = $sftp->getchmod($file);
	$output = decoct($output);
	$prm = substr($output, -3);

	$temparr = array();
	$temparr['title']=$title;
	$temparr['ftype']=$filetype;
	$temparr['fsize']=$fsize;
	$temparr['fdate']=$fdate;
	$temparr['fperm']=$prm;
	return $temparr;
}


//email specified file to the specified email address
function email_file($file, $email, $ftp_server, $ftp_user_name, $ftp_user_pass)
{
	global $sftp;

	$fileatt_type = "application/octet-stream"; // File Type
	$fileatt_name = basename($file); // Filename that will be used for the file as the attachment

	$email_from = $email; // Who the email is from
	$email_subject = basename($file)." sent via OnlineFTP from sftp://".$ftp_server; // The Subject of the email
	$email_message = "You have been sent a file from OnlineFTP. Please see the file attached to this message.\n\n - \nFor more information on OnlineFTP, please visit http://seanybob.net/onlineftp"; // Message that the email has in it

	$email_to = $email; // Who the email is too

	$headers = "From: ".$email_from;

	$data = $sftp->get($file);

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
function show_directory($directory, $conn_id="")
{
	global $sftp;
	if($directory){$adddir = $directory."/";$fulldir = $adddir;}
	if($directory == '') { $directory = "."; }

	$ddata = $sftp->rawlist($adddir.$thisdirectory);

	// get contents of the current directory
	$contents = $sftp->nlist($directory);

	// output $contents
	asort($contents);
	if($directory != ".")
	{
		$parentDirectory = preg_replace("/\/([^\/]*)$/","",$directory);
	}

	$returnarr = array();
	foreach($contents AS $thisdirectory)
	{
		if($thisdirectory=='.' || $thisdirectory=='..'){$adddir='';}
		else{$adddir=$fulldir;}
		$title = preg_replace("/.*\//","",$thisdirectory);

		$filedata = $ddata["$title"];
		$fisize = $filedata['size'];
		$fidate = $filedata['mtime'];
		$output = $filedata['permissions'];
		$output = decoct($output);
		$fiperm = substr($output, -3);
		$dirornot = substr($output, 0, -3); //get prefix

		if ($dirornot == "100")  //then it's a file - if prefix is 40, is a directory
		{

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
		else
		{

			$temparr = array();
			$temparr['title']=$title;
			$temparr['ftype']='directory';
			$returnarr[]=$temparr;
		}
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