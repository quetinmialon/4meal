import Ajv from 'ajv'
import { describe, expect, it } from 'vitest'
import schema from '@supmeal/supmeal-1.0.schema.json'
import invalidReference from '@supmeal/examples/invalid-reference.json'
import invalidUnknownVersion from '@supmeal/examples/invalid-unknown-version.json'
import validComplete from '@supmeal/examples/valid-complete.json'
import validMinimal from '@supmeal/examples/valid-minimal.json'

type TestDocument = {
  [key: string]: unknown
  cookbooks: Array<{ id: string; recipe_ids: string[] }>
  recipes: Array<Record<string, unknown> & { id: string; cookbook_ids: string[] }>
}

const examples: Record<string, TestDocument> = {
  'invalid-reference.json': invalidReference as TestDocument,
  'invalid-unknown-version.json': invalidUnknownVersion as TestDocument,
  'valid-complete.json': validComplete as TestDocument,
  'valid-minimal.json': validMinimal as TestDocument,
}
const loadExample = (name: string) => examples[name]!

const ajv = new Ajv({ allErrors: true })
ajv.addFormat('date-time', /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:?\d{2})$/)
ajv.addFormat('uri', /^(?:https?|ftp):\/\/[^\s]+$/)
const validateSchema = ajv.compile(schema)

function validateIntegrity(document: {
  cookbooks: Array<{ id: string; recipe_ids: string[] }>
  recipes: Array<{ id: string; cookbook_ids: string[] }>
}): boolean {
  const cookbookIds = new Set(document.cookbooks.map((cookbook) => cookbook.id))
  const recipeIds = new Set(document.recipes.map((recipe) => recipe.id))

  return cookbookIds.size === document.cookbooks.length
    && recipeIds.size === document.recipes.length
    && document.cookbooks.every((cookbook) => cookbook.recipe_ids.every((id) => recipeIds.has(id)))
    && document.recipes.every((recipe) => recipe.cookbook_ids.every((id) => cookbookIds.has(id)))
    && document.cookbooks.every((cookbook) => cookbook.recipe_ids.every((recipeId) =>
      document.recipes.find((recipe) => recipe.id === recipeId)?.cookbook_ids.includes(cookbook.id) ?? false,
    ))
    && document.recipes.every((recipe) => recipe.cookbook_ids.every((cookbookId) =>
      document.cookbooks.find((cookbook) => cookbook.id === cookbookId)?.recipe_ids.includes(recipe.id) ?? false,
    ))
}

describe('SUPMEAL 1.0 JSON Schema', () => {
  it.each(['valid-minimal.json', 'valid-complete.json'])('accepts %s', (file) => {
    const document = loadExample(file)
    expect(validateSchema(document), JSON.stringify(validateSchema.errors)).toBe(true)
    expect(validateIntegrity(document)).toBe(true)
  })

  it('rejects an unknown format version', () => {
    const document = loadExample('invalid-unknown-version.json')
    expect(validateSchema(document)).toBe(false)
    expect(validateSchema.errors?.some((error) => error.dataPath === '.version')).toBe(true)
  })

  it('rejects a structurally valid document with dangling or asymmetric references', () => {
    const document = loadExample('invalid-reference.json')
    expect(validateSchema(document), JSON.stringify(validateSchema.errors)).toBe(true)
    expect(validateIntegrity(document)).toBe(false)
  })

  it('rejects unsupported properties and missing required recipe data', () => {
    const document = loadExample('valid-minimal.json')
    document.unexpected = true
    delete document.recipes[0]!.ingredients
    expect(validateSchema(document)).toBe(false)
  })
})
