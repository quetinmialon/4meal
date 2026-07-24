<script setup lang="ts">
import { computed, onBeforeUnmount, ref } from 'vue';

const props = withDefaults(defineProps<{
  modelValue: File | null;
  existingImageUrl?: string | null;
  disabled?: boolean;
}>(), { existingImageUrl: null, disabled: false });

const emit = defineEmits<{ 'update:modelValue': [value: File | null] }>();
const input = ref<HTMLInputElement | null>(null);
const imageError = ref('');
const isChecking = ref(false);
const localPreview = ref<string | null>(null);

const previewUrl = computed(() => localPreview.value ?? props.existingImageUrl);

function revokePreview(): void {
  if (localPreview.value !== null) {
    URL.revokeObjectURL(localPreview.value);
    localPreview.value = null;
  }
}

function checkDimensions(url: string): Promise<string | null> {
  return new Promise((resolve) => {
    const image = new Image();
    image.onload = () => {
      URL.revokeObjectURL(url);
      if (image.width < 200 || image.height < 200) {
        resolve('L’image doit mesurer au moins 200 × 200 pixels.');
      } else if (image.width > 4000 || image.height > 4000) {
        resolve('L’image ne peut pas dépasser 4000 × 4000 pixels.');
      } else {
        resolve(null);
      }
    };
    image.onerror = () => {
      URL.revokeObjectURL(url);
      resolve('Le fichier sélectionné n’est pas une image valide.');
    };
    image.src = url;
  });
}

async function handleChange(event: Event): Promise<void> {
  const selectedInput = event.target as HTMLInputElement;
  const file = selectedInput.files?.[0] ?? null;
  imageError.value = '';

  if (file === null) {
    revokePreview();
    emit('update:modelValue', null);
    return;
  }

  const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
  if (!allowedTypes.includes(file.type)) {
    imageError.value = 'Le fichier doit être au format JPEG, PNG ou WebP.';
    selectedInput.value = '';
    emit('update:modelValue', null);
    return;
  }
  if (file.size > 5 * 1024 * 1024) {
    imageError.value = 'L’image ne doit pas dépasser 5 Mo.';
    selectedInput.value = '';
    emit('update:modelValue', null);
    return;
  }

  isChecking.value = true;
  const dimensionsUrl = URL.createObjectURL(file);
  const dimensionError = await checkDimensions(dimensionsUrl);
  isChecking.value = false;
  if (dimensionError !== null) {
    imageError.value = dimensionError;
    selectedInput.value = '';
    emit('update:modelValue', null);
    return;
  }

  revokePreview();
  localPreview.value = URL.createObjectURL(file);
  emit('update:modelValue', file);
}

function removeImage(): void {
  revokePreview();
  imageError.value = '';
  if (input.value !== null) input.value.value = '';
  emit('update:modelValue', null);
}

onBeforeUnmount(revokePreview);
</script>

<template>
  <div class="recipe-image-field">
    <label for="recipe-image-input">Image de la recette</label>
    <input
      id="recipe-image-input"
      ref="input"
      type="file"
      accept="image/jpeg,image/png,image/webp"
      :disabled="disabled || isChecking"
      :aria-invalid="imageError ? 'true' : 'false'"
      aria-describedby="recipe-image-help recipe-image-error"
      @change="handleChange"
    />
    <p id="recipe-image-help" class="help-text">JPEG, PNG ou WebP, 5 Mo maximum, de 200 × 200 à 4000 × 4000 pixels.</p>
    <p v-if="isChecking" role="status">Vérification de l’image...</p>
    <p v-if="imageError" id="recipe-image-error" class="field-error" role="alert">{{ imageError }}</p>
    <div v-if="previewUrl" class="image-preview">
      <img :src="previewUrl" alt="Aperçu de l’image de la recette" />
      <button v-if="localPreview" type="button" :disabled="disabled || isChecking" @click="removeImage">Retirer l’image sélectionnée</button>
    </div>
  </div>
</template>

<style scoped>
.recipe-image-field { display: grid; gap: .5rem; }
.help-text { margin: 0; color: #50634d; font-size: .9rem; }
.image-preview { display: grid; gap: .6rem; max-width: 20rem; }
.image-preview img { display: block; width: 100%; max-height: 14rem; object-fit: cover; border-radius: .8rem; border: 1px solid rgba(86,112,79,.2); }
.image-preview button { width: fit-content; padding: .55rem .8rem; border: 1px solid #8f1e1e; border-radius: .5rem; background: transparent; color: #8f1e1e; font: inherit; cursor: pointer; }
.field-error { margin: 0; color: #8f1e1e; font-size: .9rem; }
button:disabled { cursor: wait; opacity: .55; }
</style>
