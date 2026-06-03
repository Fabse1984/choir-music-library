<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('CML_Watermarked_FPDI') && class_exists('setasign\\Fpdi\\Fpdi')) {
    class CML_Watermarked_FPDI extends \setasign\Fpdi\Fpdi {
        private $angle = 0;

        public function addSideWatermark($text, $page_width, $page_height) {
            $text = $this->encodeText($text);
            $this->SetFont('Helvetica', '', 8);
            $this->SetTextColor(90, 90, 90);
            $this->Rotate(90, 7, $page_height - 10);
            $this->Text(7, $page_height - 10, $text);
            $this->Rotate(0);
        }

        public function Rotate($angle, $x = -1, $y = -1) {
            if ($x === -1) {
                $x = $this->x;
            }

            if ($y === -1) {
                $y = $this->y;
            }

            if ($this->angle !== 0) {
                $this->_out('Q');
            }

            $this->angle = $angle;

            if ($angle !== 0) {
                $angle *= M_PI / 180;
                $cos = cos($angle);
                $sin = sin($angle);
                $cx = $x * $this->k;
                $cy = ($this->h - $y) * $this->k;
                $this->_out(sprintf('q %.5F %.5F %.5F %.5F %.5F %.5F cm 1 0 0 1 %.5F %.5F cm', $cos, $sin, -$sin, $cos, $cx, $cy, -$cx, -$cy));
            }
        }

        protected function _endpage() {
            if ($this->angle !== 0) {
                $this->angle = 0;
                $this->_out('Q');
            }

            parent::_endpage();
        }

        private function encodeText($text) {
            $converted = @iconv('UTF-8', 'windows-1252//TRANSLIT', $text);
            if (false !== $converted) {
                return $converted;
            }

            return function_exists('utf8_decode') ? utf8_decode(str_replace('·', '-', $text)) : str_replace('·', '-', $text);
        }
    }
}
