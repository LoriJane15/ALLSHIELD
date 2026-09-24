<select name="statuses[{{ $form->id }}]" class="form-select status-dropdown" required>
    <option value="approved" @selected($form->status === 'approved')>Approved</option>
    <option value="disapproved" @selected($form->status === 'disapproved')>Disapproved</option>
    <option value="to be complied" @selected($form->status === 'to be complied')>To be Complied</option>
    <option value="to be conducted" @selected($form->status === 'to be conducted')>To be Conducted</option>
</select>
<textarea name="remarks[{{ $form->id }}]" class="form-control form-control-sm mt-2" rows="2"
          maxlength="2000" placeholder="Remarks (required when not approved)">{{ old("remarks.{$form->id}", $form->remarks) }}</textarea>
