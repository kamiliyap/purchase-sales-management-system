<div class="row g-4">
    <div class="col-md-6">
        <label for="sku" class="form-label">SKU <span class="text-danger">*</span></label>
        <input type="text" id="sku" name="sku" class="form-control @error('sku') is-invalid @enderror" value="{{ old('sku', $barang->sku ?? '') }}" placeholder="Contoh: BRG-001" maxlength="255" required aria-describedby="sku-help @error('sku') sku-error @enderror" @error('sku') aria-invalid="true" @enderror>
        <div class="form-text" id="sku-help">Gunakan kode unik untuk setiap barang.</div>
        @error('sku')<div class="invalid-feedback" id="sku-error">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <label for="satuan" class="form-label">Satuan <span class="text-danger">*</span></label>
        <input type="text" id="satuan" name="satuan" class="form-control @error('satuan') is-invalid @enderror" value="{{ old('satuan', $barang->satuan ?? '') }}" placeholder="Contoh: pcs, kg, atau box" maxlength="255" required @error('satuan') aria-invalid="true" aria-describedby="satuan-error" @enderror>
        @error('satuan')<div class="invalid-feedback" id="satuan-error">{{ $message }}</div>@enderror
    </div>
    <div class="col-12">
        <label for="nama_barang" class="form-label">Nama Barang <span class="text-danger">*</span></label>
        <input type="text" id="nama_barang" name="nama_barang" class="form-control @error('nama_barang') is-invalid @enderror" value="{{ old('nama_barang', $barang->nama_barang ?? '') }}" placeholder="Nama lengkap barang" maxlength="255" required @error('nama_barang') aria-invalid="true" aria-describedby="nama_barang-error" @enderror>
        @error('nama_barang')<div class="invalid-feedback" id="nama_barang-error">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <label for="harga_beli" class="form-label">Harga Beli (Rp) <span class="text-danger">*</span></label>
        <input type="number" id="harga_beli" name="harga_beli" class="form-control @error('harga_beli') is-invalid @enderror" value="{{ old('harga_beli', $barang->harga_beli ?? '') }}" placeholder="0.00" min="0" max="99999999.99" step="0.01" required @error('harga_beli') aria-invalid="true" aria-describedby="harga_beli-error" @enderror>
        @error('harga_beli')<div class="invalid-feedback" id="harga_beli-error">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <label for="stok" class="form-label">Stok <span class="text-danger">*</span></label>
        <input type="number" id="stok" name="stok" class="form-control @error('stok') is-invalid @enderror" value="{{ old('stok', $barang->stok ?? 0) }}" min="0" max="2147483647" step="1" required @error('stok') aria-invalid="true" aria-describedby="stok-error" @enderror>
        @error('stok')<div class="invalid-feedback" id="stok-error">{{ $message }}</div>@enderror
    </div>
</div>
