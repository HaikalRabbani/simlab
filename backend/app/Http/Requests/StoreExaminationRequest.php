<?php

namespace App\Http\Requests;

use App\Models\Examination;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class StoreExaminationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Otorisasi role ditangani policy di controller
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'client_uuid' => 'nullable|string|max:64',
            'schedule_id' => 'nullable|exists:screening_schedules,id',
            'student_id' => 'required|exists:students,id',
            'waktu_input' => 'nullable|date',
            'pendamping' => 'required|string|max:255',
            'catatan_petugas' => 'nullable|string',
            'strip_lot_code' => 'required|string|max:64',
            'strip_expiry_date' => 'required|date|after:today',
            'hasil' => 'required|array|size:7',
            'hasil.*.parameter' => ['required', 'in:'.implode(',', Examination::PARAMETERS)],
            'hasil.*.hasil' => ['required', 'in:'.implode(',', Examination::HASIL)],
            'rencana_tindak_lanjut' => ['nullable', 'in:'.implode(',', Examination::RENCANA_TINDAK_LANJUT)],
            'sampel_disegel' => 'nullable|boolean',
            'kode_segel' => 'nullable|string|max:64',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $hasil = (array) $this->input('hasil', []);
            $parameters = array_map(fn ($item) => $item['parameter'] ?? null, $hasil);

            // Aturan 1: 7 parameter wajib terisi & tidak boleh ada yang dobel
            if (count($parameters) !== count(array_unique($parameters))) {
                $validator->errors()->add('hasil', 'Tiap parameter uji hanya boleh diisi satu kali.');
            }

            $hasPositif = in_array('positif', array_column($hasil, 'hasil'), true);

            // Aturan 3: ada >=1 positif -> rencana_tindak_lanjut & sampel_disegel wajib
            if ($hasPositif) {
                if (! in_array($this->input('rencana_tindak_lanjut'), Examination::RENCANA_TINDAK_LANJUT, true)) {
                    $validator->errors()->add('rencana_tindak_lanjut', 'Wajib diisi bila ada parameter reaktif.');
                }
                if (! $this->has('sampel_disegel')) {
                    $validator->errors()->add('sampel_disegel', 'Wajib diisi bila ada parameter reaktif.');
                }
                // Bila sampel disegel, kode segel wajib
                if ($this->boolean('sampel_disegel') && ! $this->filled('kode_segel')) {
                    $validator->errors()->add('kode_segel', 'Kode segel wajib diisi bila sampel disegel.');
                }
            }
        });
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new ValidationException($validator);
    }
}
