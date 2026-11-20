<?php

namespace App\Services\Support;

use App\Models\Category;
use App\Models\Room;
use App\Models\SupportSession;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ConversationContext
{
    public function wantsReport(string $content, bool $commandOnly = false): bool
    {
        $text = trim(Str::ascii(mb_strtolower($content)));
        if (preg_match('/\b(neu|gia su|la gi|nhu the nao|bang cach nao|khi nao|mat phi|bao lau)\b/', $text) || $this->cancelsReport($content)) {
            return false;
        }

        $recipient = '(?:ktv|it|ky thuat vien|bo phan (?:it|ky thuat)|nhan vien ky thuat)';
        $request = '(?:(?:gui|tao|lap) (?:giup (?:toi|minh) )?(?:bao cao|phieu|yeu cau ho tro)'
            .'|bao cao (?:giup|ho) (?:toi|minh)'
            .'|(?:bao(?: cao)?|chuyen(?: (?:su co|yeu cau|phieu|bao cao))?)(?: (?:len|cho|toi|den))? '.$recipient
            .'|(?:goi|gap|nho|lien he) '.$recipient.'(?: (?:ho tro|xu ly|kiem tra))?)';
        $suffix = $commandOnly ? '(?: (?:giup|ho|cho) (?:toi|minh))?(?: (?:nhe|nha|di|voi|a))*(?: (?:co )?duoc (?:khong|chu))?[.!?\s]*$' : '(?:\b|$)';
        $clauses = preg_split('/[,;\n]+/', $text);
        if ($commandOnly && count($clauses) !== 1) {
            return false;
        }
        foreach ($clauses as $clause) {
            if (preg_match('/^(?:(?:ban |ai |oi |hay |nho ban |toi muon |minh muon |lam on |vui long )*)'.$request.$suffix.'/', trim($clause))) {
                return true;
            }
        }

        return false;
    }

    public function cancelsReport(string $content): bool
    {
        foreach (preg_split('/[,;\n]+/', Str::ascii(mb_strtolower($content))) as $clause) {
            if (preg_match('/^(?:(?:nhung|va|ban|ai|toi|minh|em|hay|vui long) )*(?:khong(?: muon| can)?|dung|chua(?: muon| can)?|huy)\b.*\b(gui|bao|chuyen|goi|gap|phieu)\b/', trim($clause))) {
                return true;
            }
        }

        return false;
    }

    public function isResolved(string $content): bool
    {
        return (bool) preg_match('/^(da duoc|da khac phuc|da hoat dong|het loi roi|toi da sua duoc|minh da sua duoc)[.!\s]*$/', trim(Str::ascii(mb_strtolower($content))));
    }

    public function incidentFacts(SupportSession $session): Collection
    {
        $labels = array_filter([$session->room_label, $session->room_code, Category::find($session->category_id)?->name]);

        return $session->messages()->where('role', 'user')->orderBy('id')->pluck('content_text')->filter(function ($content) use ($labels) {
            if ($this->wantsReport($content, commandOnly: true) || $this->isResolved($content) || $this->cancelsReport($content)) {
                return false;
            }
            $text = Str::ascii(mb_strtolower($content));
            foreach ($labels as $label) {
                $text = str_replace(Str::ascii(mb_strtolower($label)), '', $text);
            }
            $text = preg_replace('/\b(o|tai|phong|danh muc|thiet bi)\b/', '', $text);

            $plain = trim(preg_replace('/\s+/', ' ', preg_replace('/[^a-z0-9\s]/', ' ', $text)));
            if (preg_match('/^(?:(?:xin )?chao(?: ban)?|hi|hello|(?:toi|minh|em)(?: dang)?(?: can (?:ho tro|(?:ban )?giup(?: do)?))?)(?: (?:nhe|nha|voi|a))*$/', $plain)) {
                return false;
            }

            return trim(preg_replace('/[^a-z0-9]/', '', $text)) !== '';
        })->values();
    }

    public function clarification(SupportSession $session): ?string
    {
        if (! $session->room_id && ! $session->category_id) {
            return 'Trước khi hỗ trợ, bạn đang ở phòng nào và cần hỗ trợ công việc hoặc thiết bị gì? Hãy mô tả vấn đề cụ thể; bạn cũng có thể chọn phòng và danh mục bên dưới.';
        }
        if (! $session->room_id) {
            return 'Bạn cần hỗ trợ tại phòng nào? Cho mình biết mã phòng hoặc chọn phòng bên dưới để hướng dẫn đúng địa điểm.';
        }
        if (! $session->category_id) {
            return 'Bạn cần hỗ trợ công việc hoặc thiết bị gì tại phòng '.$session->room_code.'? Hãy mô tả vấn đề cụ thể và chọn danh mục phù hợp bên dưới.';
        }
        if ($this->incidentFacts($session)->isEmpty()) {
            return 'Mình đã biết phòng '.$session->room_code.' và danh mục '.Category::find($session->category_id)?->name.'. Bạn mô tả công việc cần hỗ trợ hoặc dấu hiệu lỗi cụ thể để mình bắt đầu nhé.';
        }

        return null;
    }

    public function update(SupportSession $session, array $input): void
    {
        $text = Str::ascii(mb_strtolower($input['content']));
        $room = isset($input['room_id']) ? Room::with('building')->findOrFail($input['room_id']) : null;
        if (! $room && ! $session->room_id) {
            $matches = Room::with('building')->get()->filter(function ($candidate) use ($text) {
                return collect([$candidate->code, $candidate->name])->filter()->contains(fn ($label) => preg_match('/(?<![a-z0-9])'.preg_quote(Str::ascii(mb_strtolower($label)), '/').'(?![a-z0-9])/', $text) === 1);
            });
            if ($matches->count() === 1) {
                $room = $matches->first();
            }
        }
        if ($room) {
            $session->fill(['room_id' => $room->id, 'room_label' => $room->name, 'room_code' => $room->code, 'building_label' => $room->building?->name]);
        }
        if (isset($input['category_id'])) {
            $session->category_id = Category::findOrFail($input['category_id'])->id;
        } elseif (! $session->category_id) {
            $matches = Category::all()->filter(fn ($category) => str_contains($text, Str::ascii(mb_strtolower($category->name))));
            if ($matches->count() === 1) {
                $session->category_id = $matches->first()->id;
            }
        }
    }
}
