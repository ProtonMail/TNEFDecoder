<?php namespace TNEFDecoder;

/**
 * SquirrelMail TNEF Decoder Plugin
 *
 * Copyright (c) 2010- Paul Lesniewski <paul@squirrelmail.org>
 * Copyright (c) 2003  Bernd Wiegmann <bernd@wib-software.de>
 * Copyright (c) 2002  Graham Norburys <gnorbury@bondcar.com>
 *
 * Licensed under the GNU GPL. For full terms see the file COPYING.
 *
 * @package plugins
 * @subpackage tnef_decoder
 *
 */
class TNEFFileRTF extends TNEFFileBase
{
   var $size;
   var $debug;
   const MAX_DICT_SIZE = 4096;
   const INIT_DICT_SIZE = 207;

   function __construct($debug, $data)
   {
      parent::__construct($debug);
      $this->type = "application/rtf";
      $this->name = "EmbeddedRTF.rtf";
      $this->debug = $debug;

      $this->decode_crtf(new TNEFBuffer($data));
   }

   function getSize()
   {
      return $this->size;
   }

   function decode_crtf(TNEFBuffer $buffer)
   {
      $size_compressed = tnef_geti32($buffer);
      $this->size = tnef_geti32($buffer);
      $magic = tnef_geti32($buffer);
      $crc32 = tnef_geti32($buffer);

      if ($this->debug)
         tnef_log("CRTF: size comp=$size_compressed, size=$this->size");

      $data = tnef_getx($buffer->getRemainingBytes(), $buffer);

      switch ($magic) {
         case CRTF_COMPRESSED:
            $this->uncompress($data);
            break;

         case CRTF_UNCOMPRESSED:
            $this->content = $data;
            break;

         default:
            if ($this->debug)
               tnef_log("Unknown Compressed RTF Format");
            break;
      }
   }

   function uncompress($data)
   {
      $preload = "{\\rtf1\\ansi\\mac\\deff0\\deftab720{\\fonttbl;}{\\f0\\fnil \\froman \\fswiss \\fmodern \\fscript \\fdecor MS Sans SerifSymbolArialTimes New RomanCourier{\\colortbl\\red0\\green0\\blue0\n\r\\par \\pard\\plain\\f0\\fs20\\b\\i\\u\\tab\\tx";
      $length_preload = strlen($preload);
      $init_dict = [];
      for ($cnt = 0; $cnt < $length_preload; $cnt++) {
         $init_dict[$cnt] = $preload[$cnt];
      }
      $init_dict = array_merge($init_dict, array_fill(count($init_dict), self::MAX_DICT_SIZE - $length_preload, ' '));
      $write_offset = self::INIT_DICT_SIZE;
      $output_buffer = [];
      $end = false;
      $in = 0;
      $l = strlen($data);
      while (!$end) {
         if ($in >= $l) {
            break;
         }
          $control = strrev(str_pad(decbin(ord($data[$in++])), 8, 0, STR_PAD_LEFT));
         for ($i = 0; $i < 8; $i++) {
            if ($control[$i] == '1') {
                $token = unpack("n", $data[$in++] . $data[$in++])[1];
                $offset = ($token >> 4) & 0b111111111111;
                $length = $token & 0b1111;
                if ($write_offset == $offset) {
                    $end = true;
                    break;
                }
                $actual_length = $length + 2;
                for ($step = 0; $step < $actual_length; $step++) {
                    $read_offset = ($offset + $step) % self::MAX_DICT_SIZE;
                    $char = $init_dict[$read_offset];
                    $output_buffer[] = $char;
                  $init_dict[$write_offset] = $char;
                  $write_offset = ($write_offset + 1) % self::MAX_DICT_SIZE;
               }
            } else {
                if ($in >= $l) {
                    break;
                }
                $val = $data[$in++];
                $output_buffer[] = $val;
                $init_dict[$write_offset] = $val;
                $write_offset = ($write_offset + 1) % self::MAX_DICT_SIZE;
            }
         }
      }
      $this->content = implode('', $output_buffer);
   }

}



