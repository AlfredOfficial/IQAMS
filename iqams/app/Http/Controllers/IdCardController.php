<?php

namespace App\Http\Controllers;

use App\Services\QrCredentialService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class IdCardController extends Controller
{
    public function show(Request $request, QrCredentialService $credentials): JsonResponse
    {
        $user = $request->user()->loadMissing([
            'role',
            'student.course.department',
            'instructor.department',
            'nonTeachingStaff.department',
        ]);

        $card = match ($user->role?->role_name) {
            'student' => $this->studentCard($user->student),
            'instructor' => $this->instructorCard($user->instructor),
            'staff' => $this->staffCard($user->nonTeachingStaff),
            default => abort(403, 'ID cards are not available for this account role.'),
        };

        abort_unless($card['profile'], 403, 'No profile is linked to this account.');

        $profile = $card['profile'];
        $qrCode = $credentials->plainText($credentials->activeFor($user));

        if (trim($qrCode) === '') {
            return response()->json(['message' => 'No QR code is assigned to your account.'], 422);
        }

        return response()->json([
            'name' => Str::squish($profile->fullName()),
            'role' => $card['role'],
            'identifier_label' => $card['identifier_label'],
            'identifier' => $card['identifier'],
            'department' => $card['department'] ?: 'Department not assigned',
            'year_level' => $card['year_level'],
            'qr_code' => $qrCode,
            'avatar_url' => $user->avatar_url ?: asset('images/default-avatar.svg'),
            'logo_url' => asset('favicon.svg'),
            'filename' => $card['filename'],
        ])->header('Cache-Control', 'private, no-store');
    }

    private function studentCard($student): array
    {
        $identifier = (string) ($student?->student_no ?? '');

        return [
            'profile' => $student,
            'role' => 'Student',
            'identifier_label' => 'Student ID',
            'identifier' => $identifier,
            'department' => $student?->course?->department?->department_name,
            'year_level' => $student?->year_level ? $this->yearLevel($student->year_level) : null,
            'filename' => $this->filename('STUDENT', $identifier),
        ];
    }

    private function instructorCard($instructor): array
    {
        $identifier = (string) ($instructor?->employee_no ?? '');

        return [
            'profile' => $instructor,
            'role' => 'Teaching Personnel',
            'identifier_label' => 'Employee ID',
            'identifier' => $identifier,
            'department' => $instructor?->department?->department_name,
            'year_level' => null,
            'filename' => $this->filename('INSTRUCTOR', $identifier),
        ];
    }

    private function staffCard($staff): array
    {
        $identifier = (string) ($staff?->employee_no ?? '');

        return [
            'profile' => $staff,
            'role' => 'Non-Teaching Personnel',
            'identifier_label' => 'Employee ID',
            'identifier' => $identifier,
            'department' => $staff?->department?->department_name,
            'year_level' => null,
            'filename' => $this->filename('STAFF', $identifier),
        ];
    }

    private function yearLevel(int|string $year): string
    {
        $number = (int) $year;
        $suffix = match (true) {
            $number % 100 >= 11 && $number % 100 <= 13 => 'th',
            $number % 10 === 1 => 'st',
            $number % 10 === 2 => 'nd',
            $number % 10 === 3 => 'rd',
            default => 'th',
        };

        return $number.$suffix.' Year';
    }

    private function filename(string $prefix, string $identifier): string
    {
        $safeIdentifier = Str::of($identifier)->upper()->replaceMatches('/[^A-Z0-9_-]+/', '-')->trim('-')->value();

        return $prefix.'-'.($safeIdentifier ?: 'UNKNOWN').'-IQAMS-ID.png';
    }
}
